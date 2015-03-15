<?php

/**
 * HTML cache refreshing and -invalidation job for RESTBase.
 *
 * This job comes in a few variants:
 *   - a) Recursive jobs to purge caches for backlink pages for a given title.
 *        They have have (type:OnDependencyChange,recursive:true,table:<table>) set.
 *   - b) Jobs to purge caches for a set of titles (the job title is ignored).
 *	      They have have (type:OnDependencyChange,pages:(<page ID>:(<namespace>,<title>),...) set.
 *   - c) Jobs to purge caches for a single page (the job title)
 *        They have (type:OnEdit) set.
 */
class RestbaseUpdateJob extends Job {


	function __construct( $title, $params, $id = 0 ) {

		// Map old jobs to new 'OnEdit' jobs
		if ( !isset( $params['type'] ) ) {
			$params['type'] = 'OnEdit'; // b/c
		}

		parent::__construct( 'RestbaseUpdateJob' . $params['type'], $title, $params, $id );

		if ( $params['type'] == 'OnEdit' ) {
			// Simple duplicate removal for single-title jobs. Other jobs are
			// deduplicated with root job parameters.
			$this->removeDuplicates = true;
		}

	}


	/**
	 * Constructs the URL prefix for RESTBase and caches it
	 * @return string RESTBase's URL prefix
	 */
	private static function getRestbasePrefix() {

		static $prefix = null;
		// set the static variable so as not to construct
		// the prefix URL every time
		if ( is_null( $prefix ) ) {
			global $wgRestbaseServer, $wgRestbaseAPIVersion,
				$wgRestbaseDomain, $wgCanonicalServer;
			if ( !isset( $wgRestbaseDomain ) || is_null( $wgRestbaseDomain ) ) {
				$wgRestbaseDomain = preg_replace(
					'/^(https?:\/\/)?([^\/:]+?)(\/|:\d+\/?)?$/',
					'$2',
					$wgCanonicalServer
				);
			}
			$prefix = implode( '/', array(
				$wgRestbaseServer,
				$wgRestbaseDomain,
				$wgRestbaseAPIVersion
			) );
		}

		return $prefix;

	}


	/**
	 * Construct a revision ID invalidation URL
	 *
	 * @param $revid integer the revision ID to invalidate
	 * @return string an absolute URL for the revision
	 */
	private static function getRevisionURL( $revid ) {

		// construct the URL
		return implode( '/', array( self::getRestbasePrefix(),
			'page', 'revision', $revid ) );

	}


	/**
	 * Construct a page title invalidation URL
	 *
	 * @param $title Title
	 * @param $revid integer the revision ID to use
	 * @return string an absolute URL for the article
	 */
	private static function getPageTitleURL( Title $title, $revid ) {

		// construct the URL
		return implode( '/', array( self::getRestbasePrefix(), 'page',
			'html', wfUrlencode( $title->getPrefixedDBkey() ), $revid ) );

	}


	function run() {

		global $wgRestbaseUpdateTitlesPerJob, $wgUpdateRowsPerJob;

		if ( $this->params['type'] === 'OnEdit' ) {
			// there are two cases here:
			// a) this is a rev_visibility action
			// b) this is some type of a page edit
			if ( $this->params['mode'] === 'rev_visibility' ) {
				$this->signalRevChange();
			} else {
				$this->invalidateTitle();
			}
		} elseif ( $this->params['type'] === 'OnDependencyChange' ) {
			// recursive update of linked pages
			static $expected = array( 'recursive', 'pages' ); // new jobs have one of these
			if ( !array_intersect( array_keys( $this->params ), $expected ) ) {
				// Old-style job; discard
				return true;
			}
			// Job to purge all (or a range of) backlink pages for a page
			if ( !empty( $this->params['recursive'] ) ) {
				// Convert this into some title-batch jobs and possibly a
				// recursive RestbaseUpdateJob job for the rest of the backlinks
				$jobs = BacklinkJobUtils::partitionBacklinkJob(
					$this,
					$wgUpdateRowsPerJob,
					$wgRestbaseUpdateTitlesPerJob, // jobs-per-title
					// Carry over information for de-duplication
					array(
						'params' => $this->getRootJobParams() + array(
							'table' => $this->params['table'], 'type' => 'OnDependencyChange' )
					)
				);
				JobQueueGroup::singleton()->push( $jobs );
			} elseif ( isset( $this->params['pages'] ) ) {
				$this->invalidateTitles( $this->params['pages'] );
			}
		}

		return true;

	}


	/**
	 * Dispatches the request(s) using MultiHttpClient, waits for the result(s),
	 * checks them and sets the error flag if needed
	 * @param $reqs array an array of request maps to dispatch
	 * @return boolean whether all of the requests have been executed successfully
	 */
	protected function dispatchRequests( array $reqs ) {

		// create a new MultiHttpClient instance with default params
		$http = new MultiHttpClient( array( 'maxConnsPerHost' => count( $reqs ) ) );

		// send the requests and wait for responses
		$reqs = $http->runMulti( $reqs );

		// check for errors
		foreach( $reqs as $k => $arr ) {
			if ( $reqs[$k]['response']['error'] != '' ) {
				$this->setLastError( $reqs[$k]['response']['error'] );
				return false;
			}
		}

		// ok, all good
		return true;

	}


	/**
	 * Signals to RESTBase a change has happened in the
	 * visibility of a revision
	 */
	protected function signalRevChange() {

		// construct the requests
		$requests = array();
		foreach( $this->params['revs'] as $revid ) {
			$requests[] = array(
				'method' => 'GET',
				'url' => self::getRevisionURL( $revid ),
				'headers' => array(
					'Cache-control' => 'no-cache'
				)
			);
		}

		// dispatch the requests
		///wfDebug( "RestbaseUpdateJob::signalRevChange: " . json_encode( $requests ) . "\n" );
		$this->dispatchRequests( $requests );

		return $this->getLastError() == null;

	}


	/**
	 * Invalidate a single title object after an edit. Send headers that let
	 * RESTBase/Parsoid reuse transclusion and extension expansions.
	 */
	protected function invalidateTitle() {

		$title = $this->title;
		$latest = $title->getLatestRevID();
		$previous = $title->getPreviousRevisionID( $latest );

		$requests = array( array(
			'method' => 'GET',
			'url'     => self::getPageTitleURL( $title, $latest ),
			'headers' => array(
				'X-Restbase-ParentRevision' => $previous,
				'Cache-control' => 'no-cache'
			)
		) );
		///wfDebug( "RestbaseUpdateJob::invalidateTitle: " . json_encode( $requests ) . "\n" );
		$this->dispatchRequests( $requests );

		return $this->getLastError() == null;

	}


	/**
	 * Invalidate an array (or iterator) of Title objects, right now.
	 * @param $pages array (page ID => (namespace, DB key)) mapping
	 */
	protected function invalidateTitles( array $pages ) {

		$mode = $this->params['table'] == 'templatelinks' ?
			'templates' : 'files';

		// Build an array of update requests
		$requests = array();
		foreach ( $pages as $id => $nsDbKey ) {
			$title = Title::makeTitle( $nsDbKey[0], $nsDbKey[1] );
			$latest = $title->getLatestRevID();
			$url = self::getPageTitleURL( $title, $latest );
			$requests[] = array(
				'method' => 'GET',
				'url'     => $url,
				'headers' => array(
					'X-Restbase-Mode' => $mode,
					'Cache-control' => 'no-cache'
				)
			);
		}

		// Now send off all those update requests
		$this->dispatchRequests( $requests );

		//wfDebug( 'RestbaseUpdateJob::invalidateTitles update: ' .
		//	json_encode( $requests ) . "\n" );

		return $this->getLastError() == null;

	}


}

