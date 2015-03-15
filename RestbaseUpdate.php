<?php

/**
 * Basic cache invalidation for RESTBase
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "RestbaseUpdateJobs extension\n";
	exit( 1 );
}

/**
 * Class containing basic setup functions.
 */
class RestbaseUpdateSetup {
	/**
	 * Set up RestbaseUpdate.
	 *
	 * @return void
	 */
	public static function setup() {

		global $wgAutoloadClasses, $wgJobClasses,
			$wgExtensionCredits, $wgExtensionMessagesFiles, $wgMessagesDirs;

		$dir = __DIR__;

		# Set up class autoloading
		$wgAutoloadClasses['RestbaseUpdateHooks'] = "$dir/RestbaseUpdate.hooks.php";
		$wgAutoloadClasses['RestbaseUpdateJob'] = "$dir/RestbaseUpdateJob.php";

		# Add the job types
		$wgJobClasses['RestbaseUpdateJobOnEdit'] = 'RestbaseUpdateJob';
		$wgJobClasses['RestbaseUpdateJobOnDependencyChange'] = 'RestbaseUpdateJob';

		$wgExtensionCredits['other'][] = array(
			'path' => __FILE__,
			'name' => 'RestBaseUpdateJobs',
			'author' => array(
				'Gabriel Wicke',
				'Marko Obrovac'
			),
			'version' => '0.2.1',
			'url' => 'https://www.mediawiki.org/wiki/Extension:RestBaseUpdateJobs',
			'descriptionmsg' => 'restbaseupdatejobs-desc',
			'license-name' => 'GPL-2.0+',
		);

		# Register localizations.
		$wgMessagesDirs['RestBaseUpdateJobs'] = __DIR__ . '/i18n';

		# Set up a default configuration
		self::setupDefaultConfig();

		# Now register our hooks.
		self::registerHooks();

	}


	/**
	 * Set up default config values. Override after requiring the extension.
	 *
	 * @return void
	 */
	protected static function setupDefaultConfig() {

		global $wgRestbaseServer, $wgRestbaseAPIVersion, $wgRestbaseUpdateTitlesPerJob;

		/**
		 * The RESTBase server to inform of updates.
		*/
		$wgRestbaseServer = 'http://localhost:7231';

		/**
		 * The RESTBase API version in use
		 */
		$wgRestbaseAPIVersion = 'v1';

		/**
		 * The number of recursive jobs to process in parallel
		 */
		$wgRestbaseUpdateTitlesPerJob = 50;

	}


	/**
	 * Register hook handlers.
	 *
	 * @return void
	 */
	protected static function registerHooks() {

		global $wgHooks;

		# Article edit/create
		$wgHooks['ArticleEditUpdates'][] = 'RestbaseUpdateHooks::onArticleEditUpdates';
		# Article delete/restore
		$wgHooks['ArticleDeleteComplete'][] = 'RestbaseUpdateHooks::onArticleDeleteComplete';
		$wgHooks['ArticleUndelete'][] = 'RestbaseUpdateHooks::onArticleUndelete';
		# Revision delete/restore
		$wgHooks['ArticleRevisionVisibilitySet'][] = 'RestbaseUpdateHooks::onArticleRevisionVisibilitySet';
		# Article move
		$wgHooks['TitleMoveComplete'][] = 'RestbaseUpdateHooks::onTitleMoveComplete';
		# File upload
		$wgHooks['FileUpload'][] = 'RestbaseUpdateHooks::onFileUpload';

	}


}


# Load hooks that are always set
RestbaseUpdateSetup::setup();

