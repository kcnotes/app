<?php
/**
 * SkeleSkin page
 * 
 * @author Jakub Olek <bukaj.kelo(at)gmail.com>
 * @authore Federico "Lox" Lucignano <federico(at)wikia-inc.com>
 */
class SkeleSkinService extends WikiaService {
	static private $initialized = false;
	
	private $templateObject;
	
	function init(){
		if ( !self::$initialized ) {
			F::setInstance( __CLASS__, $this );
			self::$initialized = true;
			$this->wf->LoadExtensionMessages( 'SkeleSkin' );
		}
	}
	
	/**
	 * @brief Sets the template object for internal use
	 * 
	 * @requestParam QuickTeamplate $templateObject
	 */
	public function setTemplateObject(){
		$this->templateObject = $this->getVal( 'templateObject') ;
	}
	
	public function index() {
		$jsFiles = '';
		$cssFiles = '';
		$tmpOut = new OutputPage();
		
		$tmpOut->styles = array(  ) + $this->wg->Out->styles;

		foreach( $tmpOut->styles as $style => $options ) {	
			if ( isset( $options['media'] ) || strstr( $style, 'shared' ) || strstr( $style, 'index' ) ) {
				unset( $tmpOut->styles[$style] );
			}
		}
		
		//force skin main CSS file to be the first so it will be always overridden by other files
		$cssFiles .= "<link rel=\"stylesheet\" href=\"" . AssetsManager::getInstance()->getSassCommonURL( 'skins/skeleskin/css/main.scss' ) . "\"/>";
		$cssFiles .= $tmpOut->buildCssLinks();
		
		$srcs = AssetsManager::getInstance()->getGroupCommonURL('skeleskin_js');
		//TODO: add scripts from $wgOut as needed

		foreach ( $srcs as $src ) {
			$jsFiles .= "<script type=\"{$this->wg->JsMimeType}\" src=\"$src\"></script>\n";
		}
		
		$this->mimeType = $this->templateObject->data['mimetype'];
		$this->charSet = $this->templateObject->data['charset'];
		$this->showAllowRobotsMetaTag = !$this->wg->DevelEnvironment;
		$this->pageTitle = $this->wg->Out->getPageTitle();
		$this->cssLinks = $cssFiles;
		$this->headLinks = $this->wg->Out->getHeadLinks();
		$this->languageCode = $this->templateObject->data['lang'];
		$this->languageDirection = $this->templateObject->data['dir'];
		$this->wikiHeaderContent = $this->sendRequest( 'SkeleSkinWikiHeaderService', 'index' )->toString();
		$this->pageContent = $this->sendRequest( 'SkeleSkinBodyService', 'index', array( 'bodyText' => $this->templateObject->data['bodytext'] ))->toString();
		$this->jsFiles = $jsFiles;
	}
}