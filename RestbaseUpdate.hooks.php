<?php

/**
 * Hooks for events that should trigger RESTBase updates.
 */
class RestbaseUpdateHooks {


	/**
	 * Schedule an async update job in the job queue.
	 *
	 * @param Title $title
	 * @param string $type
	 * @param array $extra_params
	 */
	private static function schedule( $title, $type, $extra_params = array() ) {

		$params = array( 'type' => $type ) + $extra_params;
		JobQueueGroup::singleton()->push( new RestbaseUpdateJob( $title, $params ) );

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
		// XXX do not forget that rev IDs are not yet actually returned
		self::schedule( $title, 'rev_delete', array( 'revs' => $revs ) );
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


}

