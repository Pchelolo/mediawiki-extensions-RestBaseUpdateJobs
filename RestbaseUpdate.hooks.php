<?php


/**
 * Hooks for events that should trigger RESTBase updates.
 */
class RestbaseUpdateHooks {


	/**
	 * Get the job parameters for a given title, job type and table name.
	 *
	 * @param Title $title
	 * @param string $type the job type (OnEdit or OnDependencyChange)
	 * @param string $table (optional for OnDependencyChange, templatelinks or
	 * imagelinks)
	 * @return Array
	 */
	private static function getJobParams( Title $title, $type, $table = null ) {

		$params = array( 'type' => $type );
		if ( $type == 'OnDependencyChange' ) {
			$params['table'] = $table;
			$params['recursive'] = true;
			return $params + Job::newRootJobParams(
				"RestbaseUpdateJob{$type}:{$table}:{$title->getPrefixedText()}:{$title->getLatestRevID()}");
		} else {
			return $params;
		}

	}


	/**
	 * Schedule an async update job in the job queue.
	 *
	 * @param Title $title
	 * @param string $action
	 * @param array $extra_params
	 */
	private static function schedule( $title, $action, $extra_params = array() ) {

		wfDebug( "RestbaseUpdateJobHook::schedule: " . $title->getText() . ' - ' . $action . "\n" );
		if ( $title->getNamespace() == NS_FILE ) {
			// File. For now we assume the actual image or file has
			// changed, not just the description page.
			$params = self::getJobParams( $title, 'OnDependencyChange', 'imagelinks' );
			$job = new RestbaseUpdateJob( $title, $params );
			JobQueueGroup::singleton()->push( $job );
			JobQueueGroup::singleton()->deduplicateRootJob( $job );
		} else {
			// Push one job for the page itself
			$params = self::getJobParams( $title, 'OnEdit' )
				+ array( 'mode' => $action ) + $extra_params;
			JobQueueGroup::singleton()->push( new RestbaseUpdateJob( $title, $params ) );
			// and one for pages transcluding this page.
			$params = self::getJobParams( $title, 'OnDependencyChange', 'templatelinks' );
			$job = new RestbaseUpdateJob( $title, $params );
			JobQueueGroup::singleton()->push( $job );
			JobQueueGroup::singleton()->deduplicateRootJob( $job );
		}

	}


	/**
	 * Callback for regular article edits
	 *
	 * @param $article WikiPage the modified wiki page object
	 * @param $editInfo
	 * @param bool $changed
	 * @return bool
	 */
	public static function onArticleEditUpdates( $article, $editInfo, $changed ) {

		if ( $changed ) {
			self::schedule( $article->getTitle(), 'edit' );
		}
		return true;

	}


	/**
	 * Callback for article deletions
	 *
	 * @param $article WikiPage the modified wiki page object
	 * @param $user User the deleting user
	 * @param string $reason
	 * @param int $id the page id
	 * @return bool
	 */
	public static function onArticleDeleteComplete( $article, $user, $reason, $id ) {

		self::schedule( $article->getTitle(), 'delete' );
		return true;

	}


	/**
	 * Callback for article undeletion. See specials/SpecialUndelete.php.
	 */
	public static function onArticleUndelete( Title $title, $created, $comment ) {

		self::schedule( $title, 'edit' );
		return true;

	}


	/**
	 * Callback for article revision changes. See
	 * revisiondelete/RevDelRevisionList.php.
	 */
	public static function onArticleRevisionVisibilitySet( $title, $revs ) {

		// TODO complete here with more info / the hidden fields perhaps ?
		self::schedule( $title, 'rev_visibility', array( 'revs' => $revs ) );
		return true;

	}


	/**
	 * Title move callback. See Title.php.
	 */
	public static function onTitleMoveComplete( $title, Title $newtitle, $user, $oldid, $newid ) {

		# Simply update both old and new title.
		self::schedule( $title, 'delete', array( 'rev' => $oldid ) );
		self::schedule( $newtitle, 'edit', array( 'rev' => $newid ) );
		return true;

	}


	/**
	 * File upload hook. See filerepo/file/LocalFile.php.
	 *
	 * XXX gwicke: This tracks file uploads including re-uploads of a new
	 * version of an image. These will implicitly also trigger null edits on
	 * the associated WikiPage (which normally exists), which then triggers
	 * the onArticleEditUpdates hook. Maybe we should thus drop this hook and
	 * simply assume that all edits to the WikiPage also change the image
	 * data.  Those edits tend to happen not long after an upload, at which
	 * point the image is likely not used in many pages.
	 */
	public static function onFileUpload( File $file ) {

		self::updateTitle( $file->getTitle(), 'file' );
		return true;

	}


}

