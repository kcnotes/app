<?php

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 * @author Krzysztof Krzyżaniak <eloy@wikia-inc.com> for Wikia.com
 * @version: 0.1
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is MediaWiki extension and cannot be used standalone.\n";
	exit( 1 ) ;
}


/**
 * @addtogroup SpecialPage
 */
class WikiFactoryPage extends SpecialPage {

	/* @var object $mWiki a row from city_list table */
	private $mWiki, $mTitle, $mDomain, $mTab, $mVariableName;
	public $mStatuses = array(-2 => 'spam', -1=> 'disabled*', "disabled", "enabled", "redirected" );
	private $mTagWikiIds = array();

	/**
	 * constructor
	 */
	function  __construct() {
		parent::__construct( "WikiFactory"  /*class*/, 'wikifactory' /*restriction*/);
	}

	/**
	 * execute
	 *
	 * main entry point
	 *
	 * @access public
	 *
	 * @param string $subpage: subpage of Title
	 *
	 * @return nothing
	 */
	public function execute( $subpage ) {
		global $wgUser, $wgOut, $wgRequest;

		if( $wgUser->isBlocked() ) {
			throw new UserBlockedError( $this->getUser()->mBlock );
		}
		if( wfReadOnly() && !wfAutomaticReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}
		if( !$wgUser->isAllowed( 'wikifactory' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->mTitle = Title::makeTitle( NS_SPECIAL, 'WikiFactory' );
		$this->mDomain = false;

		/**
		 * initial output
		 */
		$wgOut->setPageTitle( wfMsg('wikifactorypagetitle') );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		if ( strpos( $subpage, "short.stats" ) === 0 ) {
			$subpageOptions = explode( '/', $subpage );
			$lang = isset( $subpageOptions[1] ) ? $subpageOptions[1] : null;
			$wgOut->addHTML( $this->shortStats( $lang ) );
		}
		elseif ( strpos( $subpage, "long.stats" ) === 0 ) {
			$subpageOptions = explode( '/', $subpage );
			$lang = isset( $subpageOptions[1] ) ? $subpageOptions[1] : null;
			$wgOut->addHTML( $this->longStats( $lang ) );
		}
		elseif ( strtolower($subpage) === "add.variable" ) {
			$varOverrides = array();
			$wgOut->addHTML( $this->doAddVariableForm($varOverrides) ); // process the post (if relevant).
			$wgOut->addHTML( $this->addVariableForm($varOverrides) ); // display the form
		}
		else {
			$subpage = ( $subpage == "/" ) ? null : $subpage;
			$oWiki = $this->getWikiData( $subpage );

			if( !isset( $oWiki->city_id )) {
				$this->doWikiSelector();
			}
			else {
				$this->mWiki = $oWiki;
				$this->doWikiForm( );
			}
		}
	}

	/**
	 * getWikiData
	 *
	 * use subpage as param and try to find wiki which match criteria
	 *
	 * @access private
	 *
	 * @param mixed $subpage:
	 *
	 * @return mixed|int database row from city_list
	 */
	private function getWikiData( $subpage ) {
		global $wgWikiaBaseDomain;

		$domain	= $this->getRequest()->getVal( "wpCityDomain", null );
		$cityid	= $this->getRequest()->getVal( "cityid", null );
		$tab = "variables";
		if( is_null( $cityid ) && ( isset( $subpage ) || isset( $domain ) ) ) {

			/**
			 * if there is # in subpage we are switching tab
			 */
			if ( strpos( $subpage, '/' ) ) {
				$languages = Language::getLanguageNames();
				$parts = explode( '/', $subpage, 4 );
				if ( is_array( $parts ) && sizeof( $parts ) >= 2 ) {
					if ( array_key_exists( $parts[1], $languages ) ) {
						$parts[0] .= '/' . $parts[1];
						unset( $parts[1] );
						$parts = array_values( $parts );
					}
					$subpage = $parts[0];

					if ( isset( $parts[1] ) ) {
						$tab = $parts[1];

						if ( $tab === 'variables' && isset( $parts[2] ) ) {
							$this->mVariableName = trim( $parts[2] );
						}
					}
				}
			}

			if( is_null( $domain ) ) {
				$domain = $subpage;
			}

			if( ctype_digit( $subpage ) ) {
				$cityid = $subpage;
			}
			else {
				/**
				 * if $domain starts with db: it means that we want to search by database
				 * if $domain starts with id: it means that we want to search by city_id
				 */
				$cityid = null;
				if( preg_match( "/^db\:/", $domain ) ) {
					list( $prefix, $name ) = explode( ":", $domain, 2 );
					$cityid = WikiFactory::DBtoID( strtolower( $name ) );
				}
				elseif( preg_match( "/^id\:/", $domain ) ) {
					list( $prefix, $cityid ) = explode( ":", $domain, 2 );
				}
				else {
					/**
					 * if there's no dot in cityname we add .wikia.com
					 * or if is only one dot (language.domain.wikia.com)
					 */
					if( sizeof(explode(".", $domain )) <= 2 && strlen( $domain ) > 0) {
						$this->mDomain = $domain;
						$domain = $domain.".".$wgWikiaBaseDomain;
					} else {
						list( $code, $subdomain ) = explode(".", $domain, 2 );
						$exists = 0;
						if ( in_array( $code, array_keys( Language::getLanguageNames() ) ) ) {
							$exists = 1;
						} else {
							$cityid = WikiFactory::DomainToId( $domain );
							if ( !is_null($cityid) ) {
								$exists = 1;
							}
						}
						if ( $exists == 1 ) {
							$this->mDomain = $code.".".$subdomain;
							$domain = sprintf("%s.%s", $code, $subdomain );
						}
					}
					if ( is_null($cityid) ) {
						$cityid = WikiFactory::DomainToId( $domain );
					}
				}
			}
		}

		$this->mTab = $tab;
		if( !is_null( $cityid ) ) {
			$isSlot1Wiki = WikiFactory::isSlot1Wiki( $cityid );
			if ( !$isSlot1Wiki ) {
				$output = $this->getOutput();
				$output->addWikiMsg(
					'wikifactory-wiki-config-redirect',
					WikiFactory::getWikiByID( $cityid )->city_title,
					WikiFactory::cityIDtoUrl( $cityid ) . '/wiki',
					$cityid
				);
				return false;
			}
			$this->mTitle = Title::makeTitle( NS_SPECIAL, "WikiFactory/{$cityid}/{$tab}" );
		}
		if ( !isset($this->mVariableName) ) {
			$this->mVariableName = "";
		}

		return !is_null( $cityid ) ? WikiFactory::getWikiByID( $cityid ) : false;
	}

	/**
	 * doWikiSelector
	 *
	 * Pager with all wikis
	 *
	 * @access private
	 * @author eloy
	 *
	 * @return nothing
	 */
	private function doWikiSelector() {
		global $wgOut;

		$oTmpl = new EasyTemplate( dirname( __FILE__ ) . "/templates/" );
		$oTmpl->set_vars( array(
			"title"  => $this->mTitle,
			"domain" => $this->mDomain
		));

		if( !empty( $this->mDomain ) ) {
			$pager = new CityListPager( $this->mDomain );
			$oTmpl->set( "pager", $pager->render() );
		}
		$wgOut->addHTML( $oTmpl->render( "selector" ) );
	}

	/**
	 * doWikiForm
	 *
	 * show wiki data
	 *
	 * @access public
	 * @author Krzysztof Krzyżaniak <eloy@wikia.com>
	 *
	 * @return nothing
	 */
	public function doWikiForm() {
		global $wgOut, $wgRequest, $wgStylePath, $wgUser;
		global $wgDevelEnvironment;

		$info = null;


		/**
		 * check maybe something was posted
		 */
		if( $wgRequest->wasPosted() ) {
			switch( $this->mTab ) {
				case "hubs":
					$info = $this->doUpdateHubs( $wgRequest );
					break;
				case "domains":
					$info = $this->doUpdateDomains( $wgRequest );
					break;
				case "ezsharedupload":
					if($wgRequest->getVal('ezsuSave') != null) {
						$info = $this->doSharedUploadEnable( $wgRequest );
					}
					break;
			}
			Hooks::run('WikiFactory::onPostChangesApplied', array($this->mWiki->city_id));
		}

		$oTmpl = new EasyTemplate( dirname( __FILE__ ) . "/templates/" );
		$vars = array(
			"tab"         => $this->mTab,
			"hub"         => WikiFactoryHub::getInstance(),
			"wiki"        => $this->mWiki,
			"info"        => $info,
			"title"       => $this->mTitle,
			"groups"      => WikiFactory::getGroups(),
			"domains"     => WikiFactory::getDomains( $this->mWiki->city_id ),
			"protected"   => WikiFactory::getFlags ( $this->mWiki->city_id ) & WikiFactory::FLAG_PROTECTED,
			"statuses"    => $this->mStatuses,
			"variables"   => WikiFactory::getVariables(),
			"variableName"=> $this->mVariableName,
			"isDevel"     => $wgDevelEnvironment,
			'wikiFactoryUrl' => Title::makeTitle( NS_SPECIAL, 'WikiFactory' )->getFullUrl(),
			'wgStylePath' => $wgStylePath,
		);
		if( $this->mTab === 'info' ) {
			$vars[ 'founder_id' ] = $this->mWiki->city_founding_user;
			#this is the static stored email
			$vars[ 'founder_email' ] = $this->mWiki->city_founding_email;

			if( !empty( $this->mWiki->city_founding_user ) ) {
				#if we knew who they were, get their current info
				$fu = User::newFromId( $this->mWiki->city_founding_user );
				$vars[ 'founder_username' ] = $fu->getName();
				$vars[ 'founder_usermail' ] = $fu->getEmail();
				$vars[ 'founder_metrics_url' ] = $vars[ 'wikiFactoryUrl' ] . "/Metrics?founder=" . rawurlencode( $fu->getName() );
				$vars[ 'founder_usermail_metrics_url' ] = $vars[ 'wikiFactoryUrl' ] . "/Metrics?email=" . urlencode( $vars[ 'founder_usermail' ] );
				$vars[ 'founder_email_metrics_url' ] = $vars[ 'wikiFactoryUrl' ] . "/Metrics?email=" . urlencode( $vars[ 'founder_email' ] );
			} else
			{	#dont know who made the wiki, so dont try to do lookups
				$vars[ 'founder_username' ] = null;
				$vars[ 'founder_usermail' ] = null;
			}

			if( $wgUser->isAllowed( 'lookupuser' ) ) {
				$vars[ 'lookupuser_by_founder_email_url' ] = Title::newFromText( "LookupUser", NS_SPECIAL)->getFullURL(array("target" => $vars['founder_email']));

				if( !empty( $vars['founder_username'] ) ) {
					$vars[ 'lookupuser_by_founder_username_url' ] = Title::newFromText( "LookupUser", NS_SPECIAL)->getFullURL(array("target" => $vars['founder_username']));
				}
				if( !empty( $vars['founder_usermail'] ) ) {
					$vars[ 'lookupuser_by_founder_usermail_url' ] = Title::newFromText( "LookupUser", NS_SPECIAL)->getFullURL(array("target" => $vars['founder_usermail']));
				}
			}
		}
		if( $this->mTab === "hubs" ) {

			$hub = WikiFactoryHub::getInstance();
			$vars['vertical_id'] = $hub->getVerticalId( $this->mWiki->city_id );
			$vars['verticals'] = $hub->getAllVerticals();

			$wiki_old_categories = $hub->getWikiCategories ( $this->mWiki->city_id, false );
			$wiki_new_categories = $hub->getWikiCategories( $this->mWiki->city_id, true );
			$wiki_categories = array_merge($wiki_old_categories, $wiki_new_categories);

			$wiki_cat_ids = array();
			foreach ($wiki_categories as $cat) {
				$wiki_cat_ids[] = $cat['cat_id'];
			}
			$vars['wiki_categoryids'] = $wiki_cat_ids;

			$all_old_categories = $hub->getAllCategories( false );
			$all_new_categories = $hub->getAllCategories( true );
			$all_categories = array_replace($all_old_categories, $all_new_categories);

			$vars['all_categories'] = $all_categories;

		}
		if( $this->mTab === "clog" ) {
			$pager = new ChangeLogPager( $this->mWiki->city_id );
			$vars[ "changelog" ] = array(
				"limit"     => $pager->getForm(),
				"body"      => $pager->getBody(),
				"nav"       => $pager->getNavigationBar()
			);
		}
		if( $this->mTab === "ezsharedupload" ) {
			global $wgServer;
			$vars[ "EZSharedUpload" ] = array(
				"active" => WikiFactory::getVarValueByName( "wgUseSharedUploads", $this->mWiki->city_id ),
				"varTitle" => Title::makeTitle( NS_SPECIAL, 'WikiFactory' )->getFullUrl() . ( "/" . $this->mWiki->city_id . "/variables/" ),
				"info" => ( isset($info) ? $info : "" ),
				"local"  => array(
					"wgServer" => $this->mWiki->city_url,
					"wgSharedUploadDBname" => WikiFactory::getVarValueByName( "wgSharedUploadDBname", $this->mWiki->city_id ),
					"wgSharedUploadDirectory" => WikiFactory::getVarValueByName( "wgSharedUploadDirectory", $this->mWiki->city_id ),
					"wgSharedUploadPath" => WikiFactory::getVarValueByName( "wgSharedUploadPath", $this->mWiki->city_id ),
					"wgRepositoryBaseUrl" => WikiFactory::getVarValueByName( "wgRepositoryBaseUrl", $this->mWiki->city_id ),
					"wgFetchCommonsDescriptions" => WikiFactory::getVarValueByName( "wgFetchCommonsDescriptions", $this->mWiki->city_id )
			),
				"remote" => array(
					"wikiId" => 0,
					"wgServer" => "",
					"wgDBname" => "",
					"wgUploadDirectory" => "",
					"wgUploadPath" => "",
					"baseUrl" => ""
				)
			);
			if( $wgRequest->wasPosted() && $wgRequest->getVal( "ezsuWikiId" ) ) {
				$ezsuRemoteWikiId = $wgRequest->getVal( "ezsuWikiId" );
				$cityUrl = WikiFactory::cityIDtoUrl( $ezsuRemoteWikiId );

				$vars[ "EZSharedUpload" ][ "remote" ] = array(
					"wikiId" => $ezsuRemoteWikiId,
					"wgServer" => WikiFactory::cityUrlToDomain( $cityUrl ),
					"wgDBname" => WikiFactory::getWikiByID( $ezsuRemoteWikiId )->city_dbname,
					"wgUploadDirectory" => WikiFactory::getVarValueByName( "wgUploadDirectory", $ezsuRemoteWikiId ),
					"wgUploadPath" => WikiFactory::getVarValueByName( "wgUploadPath", $ezsuRemoteWikiId ),
					"baseUrl" => WikiFactory::cityUrlToDomain( $cityUrl ) . str_replace( '$1', 'File:', WikiFactory::cityUrlToArticlePath( $cityUrl, $ezsuRemoteWikiId ) )
				);
			}
		}
		$oTmpl->set_vars( $vars );
		$wgOut->addHTML( $oTmpl->render("form") );
	}

	/**
	 * doUpdateHubs
	 *
	 * Store changes connected with hubs
	 *
	 * @access private
	 *
	 * @return mixed	info when change, null when not changed
	 */
	private function doUpdateHubs( &$request ) {
		$vertical_id = $request->getVal("wpWikiVertical", null);
		$cat_ids = $request->getArray( "wpWikiCategory", array() );
		$reason = $request->getVal( "wpReason", null );
		$hub = WikiFactoryHub::getInstance();

		$hub->setVertical( $this->mWiki->city_id, $vertical_id, $reason );
		$hub->updateCategories( $this->mWiki->city_id, $cat_ids, $reason );

		return Wikia::successbox( "Vertical and Categories updated");
	}

	/**
	 * enable shared uploads on wiki
	 *
	 * @access private
	 * @param WebRequest $request
	 * @return string
	 */
	private function doSharedUploadEnable( &$request ) {
		$remoteWikiId = $request->getVal('ezsuWikiId');
		if(!empty($remoteWikiId)) {
			$cityUrl = WikiFactory::cityIDtoUrl( $remoteWikiId );
			$remoteWikiData = array(
				"wgDBname" => WikiFactory::getWikiByID( $remoteWikiId )->city_dbname,
				"wgUploadDirectory" => WikiFactory::getVarValueByName( "wgUploadDirectory", $remoteWikiId ),
				"wgUploadPath" => WikiFactory::getVarValueByName( "wgUploadPath", $remoteWikiId ),
				"baseUrl" => WikiFactory::cityUrlToDomain( $cityUrl ) . str_replace( '$1', 'File:', WikiFactory::cityUrlToArticlePath( $cityUrl, $remoteWikiId ) )
			);

			// set variables
			WikiFactory::setVarByName( "wgSharedUploadDBname", $this->mWiki->city_id, $remoteWikiData['wgDBname'] );
			WikiFactory::setVarByName( "wgSharedUploadDirectory", $this->mWiki->city_id, $remoteWikiData['wgUploadDirectory'] );
			WikiFactory::setVarByName( "wgSharedUploadPath", $this->mWiki->city_id, $remoteWikiData['wgUploadPath'] );
			WikiFactory::setVarByName( "wgRepositoryBaseUrl", $this->mWiki->city_id, $remoteWikiData['baseUrl'] );
			WikiFactory::setVarByName( "wgUseSharedUploads", $this->mWiki->city_id, true );
			WikiFactory::setVarByName( "wgFetchCommonsDescriptions", $this->mWiki->city_id, true );

			return "<span style=\"color: green; font-weight: bold;\">Saved and enabled! :)</span>";
		}
		else {
			return "<span style=\"color: red; font-weight: bold;\">Invalid data :(</span>";
		}
	}

	/**
	 * doUpdateDomains
	 *
	 * Store changes connected with domains
	 *
	 * @access private
	 * @author eloy@wikia
	 *
	 * @return mixed	info when change, null when not changed
	 */
	private function doUpdateDomains( &$request ) {
		$action = $request->getText( "wpAction", null );
		$reason = $request->getText( "wpReason", wfMsg( 'wikifactory-public-status-change-default-reason' ) );

		$message = "";
		switch( $action ) {
			case "status":
				$status = $request->getVal( "wpWikiStatus", 0 );
				WikiFactory::setPublicStatus( $status, $this->mWiki->city_id, $reason );
				$this->mWiki->city_public = $status;
				WikiFactory::clearCache( $this->mWiki->city_id );
				$message = "Status of this wiki was changed to " . $this->mStatuses[ $status ];
			case "protect":
				$protect = $request->getCheck( "wpProtected", false);
				if ($protect) {
					$message = "Wiki protected";
					WikiFactory::setFlags( $this->mWiki->city_id, WikiFactory::FLAG_PROTECTED, false, $reason );
				} else {
					$message = "Wiki un-protected";
					WikiFactory::resetFlags( $this->mWiki->city_id, WikiFactory::FLAG_PROTECTED, false, $reason );
				}
			break;
		}
		return Wikia::successmsg( $message );
	}

	/**
	 * showTab
	 *
	 * helper function, CSS/HTML code for tab
	 *
	 * @author Krzysztof Krzyżaniak <eloy@wikia.com>
	 * @access public
	 * @static
	 *
	 * @param string	$tab		current tab
	 * @param string 	$active		active tab
	 * @param integer	$city_id	wiki id in city_list table
	 * @param string	$tabtext	text to use that is not $tab
	 *
	 * @return string HTML/CSS code
	 */
	static public function showTab( $tab, $active, $city_id, $tabtext=null ) {

		$title = Title::makeTitle( NS_SPECIAL, "WikiFactory/{$city_id}/{$tab}" );

		if( empty($tabtext) ) {
			$text = wfMsg( "wikifactory-label-{$tab}" );
		}
		else {
			$text = wfMsg( "wikifactory-label-{$tabtext}" );
		}

		if( $tab === $active ) {
			#return $text;
			$attribs = array(
				"href" => $title->getFullUrl()
			);
			return Xml::element( 'a', $attribs, $text );
		}
		else {
			$attribs = array(
				"href" => $title->getFullUrl()
			);
			return Xml::element( 'a', $attribs, $text );
		}
	}

	private function shortStats( $lang = null ) {
		return $this->doStats( 90, $lang );
	}
	private function longStats( $lang = null ) {
		return $this->doStats( null, $lang );
	}

	private function doStats( $days = null, $lang = null ) {
		global $wgOut;

		$where = null;
		if( !empty($days) ) {
			$ymd = gmdate('Y-m-d', strtotime("{$days} days ago"));
			$where = array("city_created > '{$ymd}'");
		}

		if ( !empty( $lang ) ) {
			$where['city_lang'] = $lang;
		}

		$dbr = WikiFactory::db( DB_SLAVE );
		$res = $dbr->select(
			array( "city_list" ),
			array(
				"date(city_created) as date",
				"city_public",
				"count(*) as count"
			),
			$where,
			__METHOD__,
			array(
				  "GROUP BY" => "date(city_created), city_public",
				  "ORDER BY" => "date(city_created) desc"
			)
		);
		$stats = array();
		while( $row = $dbr->fetchObject( $res ) ) {
			if( !isset( $stats[ $row->date ] ) ){
				$stats[ $row->date ] = (object) null;
			}
			$stats[ $row->date ]->total += $row->count;
			switch( $row->city_public ) {
				case 1:
					$stats[ $row->date ]->active += $row->count;
					break;
				case 0:
					$stats[ $row->date ]->disabled += $row->count;
					break;
				case 2:
					$stats[ $row->date ]->redirected += $row->count;
					break;
			}
		}
		$dbr->freeResult( $res );

		$wgOut->setPageTitle( strtoupper( $lang ) . ' Wikis created daily' );

		$Tmpl = new EasyTemplate( dirname( __FILE__ ) . "/templates/" );
		$Tmpl->set( "stats", $stats );
		$Tmpl->set( "days", $days );

		return $Tmpl->render( "shortstats" );
	}

	/**
	 * Quick form for introducing a new variable to be used in WikiFactory (not setting a value).
	 *
	 * @author Sean Colombo
	 * @access private
	 *
	 * @param varOverrides array - associative array of values to put into the template.  These are assumed
	 *                             to have been loaded as a form re-initialization and are given precedence
	 *                             over the defaults.
	 *
	 * @return HTML to be rendered.
	 */
	private function addVariableForm($varOverrides = array()) {
		$oTmpl = new EasyTemplate( dirname( __FILE__ ) . "/templates/" );

		$vars = array(
			"title"         => $this->mTitle,
			"groups"        => WikiFactory::getGroups(),
			"accesslevels"  => WikiFactory::$levels,
			"types"         => WikiFactory::$types,
		);
		$vars = array_merge($vars, $varOverrides);
		$oTmpl->set_vars( $vars );

		return $oTmpl->render( "add-variable" );
	}

	/**
	 * If there was a post to the add variable form, this will process it.
	 *
	 * @author Sean Colombo
	 * @access private
	 *
	 * @param varOverrides array - array that will be filled (by reference) with any values
	 *                             which should be used as overrides for form re-initialization
	 *                             (for instance, if there was an error in the form we start where
	 *                             the user left off instead of starting from scratch).
	 *
	 * @return any additional HTML that should be rendered as a result of the form post.
	 */
	private function doAddVariableForm(&$varOverrides){
		global $wgRequest;
		$html = "";
		if( $wgRequest->wasPosted() ) {
			$cv_name = $wgRequest->getVal("cv_name");
			$cv_variable_type = $wgRequest->getVal("cv_variable_type");
			$cv_access_level = $wgRequest->getVal("cv_access_level");
			$cv_variable_group = $wgRequest->getVal("cv_variable_group");
			$cv_description = $wgRequest->getval("cv_description");
			$cv_is_unique = $wgRequest->getval("cv_is_unique", "0");

			// Verify that the form is filled out, then add the variable if it is (display an error if it isn't).
			$err = "";
			if($cv_name == ""){
				$err .= "<li>Please enter a name for the variable.</li>\n";
			}
			if(!in_array($cv_variable_type, WikiFactory::$types)){
				$err .= "<li>The value \"$cv_variable_type\" was not recognized as a valid WikiFactory::\$type.</li>\n";
			}
			if(!in_array($cv_access_level, array_keys(WikiFactory::$levels))){
				$err .= "<li>The value \"$cv_access_level\" was not recognized as a valid key from WikiFactory::\$levels.</li>\n";
			}
			if(!in_array($cv_variable_group, array_keys(WikiFactory::getGroups()))){
				$err .= "<li>The value \"$cv_variable_group\" was not recognized as a valid group_id from city_variables_groups table as returned by WikiFactory::getGroups()</li>\n";
			}
			if($cv_description == ""){
				$err .= "<li>Please enter a description of what the variable is used for.</li>\n";
			}
			if($err == ""){
				$success = WikiFactory::createVariable($cv_name, $cv_variable_type, $cv_access_level, $cv_variable_group, $cv_description, $cv_is_unique);
				if($success){
					$html .= "<div style='border:1px #0f0 solid;background-color:#afa;padding:5px'><strong>$cv_name</strong> successfully added to WikiFactory.</div>";
				} else {
					$html .= "<div style='border:1px #f00 solid;background-color:#faa;padding:5px'>";
					$html .= "<strong>ERROR: There was a database error while trying to create the variable.  Please see the logs for more info.</strong>";
					$html .= "</div>";
				}
			} else {
				$html .= "<div style='border:1px #f00 solid;background-color:#faa;padding:5px'>";
				$html .= "<strong>ERROR: Unable to add variable!</strong>";
				$html .= "<ul>\n$err</ul>\n";
				$html .= "</div>";

				$varOverrides['cv_name'] = $cv_name;
				$varOverrides['cv_variable_type'] = $cv_variable_type;
				$varOverrides['cv_access_level'] = $cv_access_level;
				$varOverrides['cv_variable_group'] = $cv_variable_group;
				$varOverrides['cv_description'] = $cv_description;
				$varOverrides['cv_is_unique'] = $cv_is_unique;
			}
		}
		return $html;
	}
}

/**
 * Changelog Pager
 */
class ChangeLogPager extends TablePager {

	public
		$mFieldNames = null,
		$mMessages = array(),
		$mQueryConds = array(),
		$mTitle,
		$mWikiId;

	/**
	 * __construct
	 *
	 * Public constructor with standard initializations
	 *
	 * @access public
	 * @author Krzysztof Krzyżaniak <eloy@wikia.com>
	 *
	 * @param integer $wiki_id	wiki identifier in wiki factory
	 *
	 */
	function __construct( $wiki_id = false ) {
		if( is_numeric( $wiki_id ) ) {
			$this->mTitle = Title::makeTitle( NS_SPECIAL, "WikiFactory/{$wiki_id}/clog" );
			$this->mWikiId = $wiki_id;
		}
		else {
			$this->mTitle = Title::makeTitle( NS_SPECIAL, "WikiFactory/change.log" );
			$this->mWikiId = false;
		}
		$this->mDefaultDirection = true;
		parent::__construct();
		// BugId: 69197 - override parent behaviour to use database with correct data instead
		global $wgExternalSharedDB;
		$this->mDb = wfGetDB( DB_SLAVE, array(), $wgExternalSharedDB);
	}

	/**
	 * getTitle
	 *
	 * @return Title object
	 */
	function getTitle() {
		return $this->mTitle;
	}

	/**
	 * getFieldNames
	 *
	 * @return Array with column names
	 */
	public function getFieldNames() {
		if( !$this->mFieldNames ) {
			$this->mFieldNames = array();
			if( ! $this->mWikiId ) {
				$this->mFieldNames["city_url"]      = "Wiki";
			}
			$this->mFieldNames["cl_timestamp"]  = "Changed";
			$this->mFieldNames["cl_type"]       = "Type";
			$this->mFieldNames["cl_user_id"]    = "Who";
			$this->mFieldNames["cl_text"]       = "What";
		}

		return $this->mFieldNames;
	}

	/**
	 * isFieldSortable
	 *
	 * @param string $field: field name
	 *
	 * @return boolean: flag if $field is sortable of not
	*/
	public function isFieldSortable( $field ) {
		static $aSortable = array( "city_url", "cl_type", "cl_timestamp", "cl_user_id" );
		return in_array( $field, $aSortable );
	}

	/**
	 * formatValue
	 *
	 * field formatter
	 *
	 * @param string $field: field name
	 * @param mixed $value: field value
	 *
	 * @return string: formated table field
	 */
	function formatValue( $field, $value ) {
		global $wgWikiaBaseDomain;
		switch ($field) {
			case "city_url":
				preg_match("/https?:\/\/([\w\.\-]+)\//", $value, $matches );
				$sRetval = str_ireplace(".".$wgWikiaBaseDomain, "", $matches[1]);
				return $sRetval;
				break;

			case "cl_text":
				return '<div class="ChangeLogPager_cl_value">' . $value . '</div>';
				break;
			case "cl_timestamp":
				return wfTimestamp( TS_EXIF, $value );
				break;
			case "cl_type":
				switch( $value ) {
					case WikiFactory::LOG_CATEGORY:
						return "category";
						break;
					case WikiFactory::LOG_VARIABLE:
						return "variable";
						break;
					case WikiFactory::LOG_DOMAIN:
						return "domain";
						break;
					case WikiFactory::LOG_STATUS:
						return "status";
						break;
				}
				break;

			case "cl_user_id":
				$oUser = User::newFromId( $value );
				$oUser->load();
				return sprintf("<a href=\"%s\">%s</a>", $oUser->getUserPage()->getLocalUrl(), $oUser->getName());
				break;

			default:
				return $value;
		}
	}

	/**
	 * getDefaultSort
	 *
	 * get default field for sorting
	 *
	 * @return string: table field
	 */
	function getDefaultSort() {
		$this->mDefaultDirection = true;
		return "cl_timestamp";
	}

	/**
	 * getQueryInfo
	 *
	 * get default field for sorting
	 *
	 * @return array: query info
	 */
	function getQueryInfo() {
		$query = array(
			"tables" => array(
				WikiFactory::table("city_list_log"),
				WikiFactory::table("city_list"),
			),
			"fields" => array( "*" ),
			"conds" => array(
				WikiFactory::table("city_list", "city_id" )
					. " = "
					. WikiFactory::table( "city_list_log", "cl_city_id" )
			)
		);

		if( $this->mWikiId ) {
			$query[ "conds" ][] = WikiFactory::table("city_list", "city_id" )
				. " = "
				. $this->mWikiId;
		}

		$variable = $this->getRequest()->getInt( 'variable' );
		if ( $variable > 0 ) {
			$query[ 'conds' ][ ] = WikiFactory::table( 'city_list_log', 'cl_type' ) . '=' . WikiFactory::LOG_VARIABLE;
			$query[ 'conds' ][ ] = WikiFactory::table( 'city_list_log', 'cl_var_id' ) . '=' . $variable;
		}

		return $query;
	}

	/**
	 * getForm
	 *
	 * get form definition
	 *
	 * @return string: empty
	 */
	function getForm() {
		return "";
	}
}


/**
 * CityListPager( $this->mDomain );
 */
class CityListPager {

	private $mPart, $mRequest, $mLimit, $mOffset, $mTemplate, $mTitle;

	/**
	 * constructor
	 *
	 * @access public
	 */
	public function __construct( $part ) {
		global $wgRequest;

		$this->mPart     = $part;
		$this->mRequest  = $wgRequest;
		$this->mLimit    = 25;
		$this->mOffset   = $this->mRequest->getVal( "offset", false );
		$this->mTitle    = Title::makeTitle( NS_SPECIAL, 'WikiFactory' );;
		$this->mTemplate = new EasyTemplate( dirname( __FILE__ ) . "/templates/" );
	}

	/**
	 * render page of pager
	 *
	 * @access public
	 */
	public function render() {

		global $wgOut;
		$this->mTemplate->set( "part",  $this->mPart );
		$this->mTemplate->set( "data",  $this->getData() );
		$this->mTemplate->set( "limit", $this->mLimit );
		$this->mTemplate->set( "title", $this->mTitle );

		return $this->mTemplate->render( "listing" );
	}

	private function getData() {

		/**
		 * build query
		 */
		wfProfileIn( __METHOD__ );
		$offset = $this->mOffset ? array( "OFFSET" => $this->mOffset ) : array();
		$dbr = WikiFactory::db( DB_SLAVE );
		$sth = $dbr->select(
			array( "city_domains" ),
			array( "distinct city_id" ),
			array( "city_domain " . $dbr->buildLike( $dbr->anyString(), $this->mPart, $dbr->anyString() ) ),
			__METHOD__,
			array(
				"ORDER BY" => "city_id",
				"LIMIT" => $this->mLimit + 1
			) + $offset
		);
		$data = array();
		$counter = 0;
		while( $row = $dbr->fetchObject( $sth ) ) {
			$obj = new stdClass;
			$obj->wiki = WikiFactory::getWikiByID( $row->city_id );
			$obj->domains = WikiFactory::getDomains( $row->city_id );
			if( $counter <= $this->mLimit ) {
				$data[] = $obj;
			}
			else {
				/**
				 * there's next page
				 */
			}
		}
		wfProfileOut( __METHOD__ );
		return $data;
	}

	static public function bold( $subject, $search ) {
		echo str_replace( $search, "<strong>{$search}</strong>", $subject );
	}
};
