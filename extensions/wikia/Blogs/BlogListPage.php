<?php
/**
 * blog listing for user, something similar to CategoryPage
 *
 * @author Krzysztof Krzyżaniak <eloy@wikia-inc.com>
 */

if ( !defined( 'MEDIAWIKI' ) ) {
    echo "This is MediaWiki extension.\n";
    exit( 1 ) ;
}

$wgHooks[ "ArticleFromTitle" ][] = "BlogListPage::hook";

class BlogListPage extends Article {

	/**
	 * overwritten Article::view function
	 */
	public function view() {
		global $wgOut, $wgUser, $wgRequest;

		$feed = $wgRequest->getText( "feed", false );
		if( $feed ) {
			$this->showFeed( $feed );
		}
		else {
			Article::view();
			$this->showBlogListing();
		}
	}

	/**
	 * take data from blog tag extension and display it
	 *
	 * @access private
	 */
	private function showBlogListing() {
		global $wgOut, $wgRequest, $wgParser, $wgMemc;

		/**
		 * use cache or skip cache when action=purge
		 */
		$user = $this->mTitle->getDBkey();
		$listing = false;
		$purge = $wgRequest->getVal( 'action' ) == 'purge';
		$offset = 0;

		if( !$purge ) {
			$listing  = $wgMemc->get( wfMemcKey( "blog", "listing", $user, $offset ) );
		}

		if( !$listing ) {
			$params = array(
				"author" => $user,
				"count"  => 50,
				"summary" => true,
				"summarylength" => 750,
				"style" => "plain",
				"title" => "Blogs",
				"timestamp" => true,
				"offset" => $offset
			);
			$listing = BlogTemplateClass::parseTag( "", $params, $wgParser );
			$wgMemc->set( wfMemcKey( "blog", "listing", $user, $offset ), $listing, 3600 );
		}
		$wgOut->addHTML( $listing );
	}

	/**
	 * generate xml feed from returned data
	 */
	private function showFeed( $feed ) {

	}

	/**
	 * static entry point for hook
	 *
	 * @static
	 * @access public
	 */
	static public function hook( &$Title, &$Article ) {
		global $wgRequest;

		/**
		 * we are only interested in User_blog:Username pages
		 */
		if( $Title->getNamespace() !== NS_BLOG_ARTICLE || $Title->isSubpage() ) {
			return true;
		}

		Wikia::log( __METHOD__, "article" );
		$Article = new BlogListPage( $Title );

		return true;
	}

}
