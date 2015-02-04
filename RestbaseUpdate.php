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
		$wgAutoloadClasses['CurlMultiClient'] = "$dir/CurlMultiClient.php";

		# Add the parsoid job types
		$wgJobClasses['RestbaseUpdateJobOnEdit'] = 'RestbaseUpdateJob';
		$wgJobClasses['RestbaseUpdateJobOnDependencyChange'] = 'RestbaseUpdateJob';
		# Old type for transition
		# @TODO: remove when old jobs are drained
		$wgJobClasses['RestabseUpdateJob'] = 'RestbaseUpdateJob';

		$wgExtensionCredits['other'][] = array(
			'path' => __FILE__,
			'name' => 'RestbaseUpdate',
			'author' => array(
				'Gabriel Wicke',
				'Marko Obrovac'
			),
			'version' => '0.2.0',
			'url' => 'https://www.mediawiki.org/wiki/Extension:RestbaseUpdateJobs',
			'descriptionmsg' => 'restbase-desc',
			'license-name' => 'GPL-2.0+',
		);

		# Register localizations.
		$wgMessagesDirs['RestbaseUpdateJobs'] = __DIR__ . '/i18n';
		$wgExtensionMessagesFiles['RestbaseUpdateJobs'] = $dir . '/RestbaseUpdate.i18n.php';

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

		global $wgRestbaseServers, $wgRestbaseDomain, $wgServer;

		/**
		 * The RESTBase server to inform of updates.
		*/
		$wgRestbaseServers = 'http://localhost:7321';

		/**
		 * This wiki's domain.
		 * Defaults to $wgServer's domain name
		*/
		$wgRestbaseDomain = preg_replace( '/^(https?:\/\/)?(.+?)\/?$/', '$2', $wgServer );

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

	}


}

# Load hooks that are always set
RestbaseUpdateSetup::setup();

