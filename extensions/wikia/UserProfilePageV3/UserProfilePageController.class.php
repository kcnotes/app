<?php

class UserProfilePageController extends WikiaController {
	const AVATAR_DEFAULT_SIZE = 150;
	const AVATAR_MAX_SIZE = 512000;
	const MAX_TOP_WIKIS = 4;

	protected $profilePage = null;
	protected $allowedNamespaces = null;
	protected $title = null;

	protected $defaultAvatars = null;
	protected $defaultAvatarPath = 'http://images.wikia.com/messaging/images/';

	public function __construct( WikiaApp $app ) {
		$this->app = $app;
		$this->allowedNamespaces = $app->getLocalRegistry()->get( 'UserProfilePageNamespaces' );
		$this->title = $app->wg->Title;

		// CSS
	}

	/**
	 * @brief main entry point
	 *
	 * @requestParam User $user user object
	 * @requestParam string $userPageBody original page body
	 * @requestParam int $wikiId current wiki id
	 */
	public function index() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = $this->getVal('user');

		$pageBody = $this->getVal( 'userPageBody' );
		$wikiId = $this->getVal( 'wikiId' );
		
		if( $this->title instanceof Title ) {
			$namespace = $this->title->getNamespace();
			$isSubpage = $this->title->isSubpage();
		} else {
			$namespace = $this->app->wg->NamespaceNumber;
			$isSubpage = false;
		}
		
		$useOriginalBody = true;

		if( $user instanceof User ) {
			$this->profilePage = F::build( 'UserProfilePage', array( 'user' =>  $user ) );
			if( $namespace == NS_USER && !$isSubpage ) {
				//we'll implement interview section later
				//$this->setVal( 'questions', $this->profilePage->getInterviewQuestions( $wikiId, true ) );
				$this->setVal( 'stuffSectionBody', $pageBody );
				$useOriginalBody = false;
			}

			$this->setVal( 'isUserPageOwner', ( ( $user->getId() == $this->wg->User->getId() ) ? true : false ) );
		}

		if( $useOriginalBody ) {
			$this->response->setBody( $pageBody );
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Renders new user identity box
	 *
	 * @desc Creates array of user's data and passes it to the template
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function renderUserIdentityBox() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$this->setVal( 'wgBlankImgUrl', $this->wg->BlankImgUrl );

		$this->app->wg->Out->addStyle(AssetsManager::getInstance()->getSassCommonURL('extensions/wikia/UserProfilePageV3/css/UserProfilePage.scss'));
		$this->wg->Out->addScriptFile( $this->wg->ExtensionsPath . '/wikia/UserProfilePageV3/js/UserProfilePage.js' );

		$sessionUser = $this->wg->User;

		$this->setRequest( new WikiaRequest($this->app->wg->Request->getValues()) );
		$user = $this->getUserFromTitle();
		
		$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
		$isUserPageOwner = (!$user->isAnon() && $user->getId() == $sessionUser->getId()) ? true : false;

		if( $isUserPageOwner ) {
//		this is a small trick to remove some
//		probably cache/session lag. before, i used
//		phalanx to block my user globally. but just
//		after i blocked him the session user object
//		still returned false when i called on it getBlockedStatus()
//		in the same time user object retrived from title
//		returned id of user block. after more or less an hour
//		both instances of User object returned me id of user block
//		when i called getBlockedStatus() on them
			$sessionUser = $user;
		}
		$userData = $userIdentityBox->setData();

		$this->setVal( 'zeroStateCssClass', ($userData['showZeroStates']) ? 'zero-state' : '');

		$this->setVal( 'user', $userData );
		$this->setVal( 'deleteAvatarLink', F::build('SpecialPage', array('RemoveUserAvatar'), 'getTitleFor')->getFullUrl('av_user='.$userData['name']) );
		$this->setVal( 'canRemoveAvatar', $sessionUser->isAllowed('removeavatar') );
		$this->setVal( 'isUserPageOwner', $isUserPageOwner );

		$canEditProfile = $isUserPageOwner || $sessionUser->isAllowed('editprofilev3');
		//if user is blocked locally (can't edit anything) he can't edit masthead too
		$canEditProfile = $sessionUser->isAllowed('edit') ? $canEditProfile : false;
		//if user is blocked globally he can't edit
		$blockId = $sessionUser->getBlockId();
		$canEditProfile = empty($blockId) ? $canEditProfile : false;
		$this->setVal( 'canEditProfile', $canEditProfile );
		$this->setVal( 'isWikiStaff', $sessionUser->isAllowed('staff') );
		$this->setVal( 'canEditProfile', ($isUserPageOwner || $sessionUser->isAllowed('staff') || $sessionUser->isAllowed('editprofilev3')) );
		
		if( !empty($this->title) ) {
			$this->setVal( 'reloadUrl', htmlentities($this->title->getFullUrl(), ENT_COMPAT, 'UTF-8') );
		} else {
			$this->setVal( 'reloadUrl', '' );
		}
		
		$this->app->wg->Out->addScript('<script type="'.$this->app->wg->JsMimeType.' src="'.$this->app->wg->StylePath.'/common/jquery/jquery.aim.js?'.$this->app->wg->StyleVersion.'"></script>');

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Renders new action button
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function renderActionButton() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$namespace = $this->title->getNamespace();

		$this->setRequest( new WikiaRequest($this->app->wg->Request->getValues()) );
		$user = $this->getUserFromTitle();
		
		$sessionUser = $this->wg->User;
		$canRename = $sessionUser->isAllowed('renameprofilev3');
		$canProtect = $sessionUser->isAllowed('protect');
		$canDelete = $sessionUser->isAllowed('deleteprofilev3');
		$isUserPageOwner = ($user instanceof User && !$user->isAnon() && $user->getId() == $sessionUser->getId()) ? true : false;

		$actionButtonArray = array();
		if( defined('NS_USER') && $namespace == NS_USER ) {
		//profile page
			$actionButtonArray = array(
				'action' => array(
					'href' => $this->title->getLocalUrl(array('action' => 'edit')),
					'text' => $this->wf->Msg('user-action-menu-edit-profile'),
				),
				'image' => MenuButtonModule::EDIT_ICON,
				'name' => 'editprofile',
			);
		} else if( defined('NS_USER_TALK') && $namespace == NS_USER_TALK && empty($this->app->wg->EnableUserTalkArchiveMode) ) {
		//talk page
			$title = F::build('Title', array($user->getName(), NS_USER_TALK), 'newFromText');

			if( $title instanceof Title ) {
			//sometimes title isn't created, i've tried to reproduce it on my devbox and i couldn't
			//checking if $title is instance of Title is a quick fix -- if it isn't no action button will be shown
				if( $isUserPageOwner ) {
					$actionButtonArray = array(
						'action' => array(
							'href' => $this->title->getLocalUrl(array('action' => 'edit')),
							'text' => $this->wf->Msg('user-action-menu-edit'),
						),
						'image' => MenuButtonModule::EDIT_ICON,
						'name' => 'editprofile',
					);
				} else {
					$actionButtonArray = array(
						'action' => array(
							'href' => $title->getLocalUrl(array('action' => 'edit', 'section' => 'new')),
							'text' => $this->wf->Msg('user-action-menu-leave-message'),
						),
						'image' => MenuButtonModule::MESSAGE_ICON,
						'name' => 'leavemessage',
						'dropdown' => array(
							'edit' => array(
								'href' => $this->title->getFullUrl(array('action' => 'edit')),
								'text' => $this->wf->Msg('user-action-menu-edit'),
							)
						),
					);
				}
			}
		} else if( defined('NS_BLOG_ARTICLE') && $namespace == NS_BLOG_ARTICLE && $isUserPageOwner ) {
		//blog page
			global $wgCreateBlogPagePreload;
			wfLoadExtensionMessages('Blogs');

			$actionButtonArray = array(
				'action' => array(
					'href' => F::build('SpecialPage', array('CreateBlogPage'), 'getTitleFor')->getLocalUrl( !empty($wgCreateBlogPagePreload) ? "preload=$wgCreateBlogPagePreload" : "" ),
					'text' => wfMsg('blog-create-post-label'),
				),
				'image' => MenuButtonModule::BLOG_ICON,
				'name' => 'createblogpost',
			);
		}

		if( defined('NS_USER') && defined('NS_USER_TALK') && in_array($namespace, array(NS_USER, NS_USER_TALK)) ) {
		//profile & talk page
			if( $canRename ) {
				$renameUrl = F::build('SpecialPage', array('MovePage'), 'getTitleFor')->getLocalUrl().'/'.$this->title->__toString();
				$actionButtonArray['dropdown']['rename'] = array(
					'href' => $renameUrl,
					'text' => $this->wf->Msg('user-action-menu-rename'),
				);
			}

			if( $canProtect ) {
				$protectStatus = $this->title->isProtected() ? 'unprotect' : 'protect';

				$actionButtonArray['dropdown']['protect'] = array(
					'href' => $this->title->getLocalUrl(array('action' => $protectStatus)),
					'text' => $this->wf->Msg('user-action-menu-'.$protectStatus),
				);
			}

			if( $canDelete ) {
				$actionButtonArray['dropdown']['delete'] = array(
					'href' => $this->title->getLocalUrl(array('action' => 'delete')),
					'text' => $this->wf->Msg('user-action-menu-delete'),
				);
			}

			$actionButtonArray['dropdown']['history'] = array(
				'href' => $this->title->getLocalUrl(array('action' => 'history')),
				'text' => $this->wf->Msg('user-action-menu-history'),
			);
		}

		$this->wf->RunHooks( 'UserProfilePageAfterGetActionButtonData', array(&$actionButtonArray, $namespace, $canRename, $canProtect, $canDelete, $isUserPageOwner) );
		
		$actionButton = $this->wf->RenderModule('MenuButton', 'Index', $actionButtonArray);
		$this->setVal('actionButton', $actionButton);

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Gets lightbox data
	 *
	 * @requestParam string $userId user's unique id
	 * @requestParam string $tab current tab
	 *
	 * @author ADi
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function getLightboxData() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$selectedTab = $this->getVal('tab');
		$userId = $this->getVal('userId');
		$wikiId = $this->wg->CityId;
		$sessionUser = $this->wg->User;
		$canEditProfile = $sessionUser->isAllowed('editprofilev3');

		//checking if user is blocked locally
		$isBlocked = !$sessionUser->isAllowed('edit');
		//checking if user is blocked globally
		$isBlocked = empty($isBlocked) ? $sessionUser->getBlockId() : $isBlocked;

		if( ($sessionUser->isAnon() && !$canEditProfile) || $isBlocked ) {
			throw new WikiaException( $this->wf->msg('userprofilepage-invalid-user') );
		} else {
			$this->profilePage = F::build( 'UserProfilePage', array('user' => $sessionUser) );

			$this->setVal( 'body', (string) $this->sendSelfRequest( 'renderLightbox', array( 'tab' => $selectedTab, 'userId' => $userId ) ) );
			//we'll implement interview section later
			//$this->setVal( 'interviewQuestions', $this->profilePage->getInterviewQuestions( $wikiId, false, true ) );
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Renders lightbox
	 *
	 * @requestParam string $userId user's unique id
	 * @requestParam string $tab current tab
	 *
	 * @desc Mainly passes two variables to the template: tabs, selectedTab but also if it's about tab or avatar uses private method to pass more data
	 */
	public function renderLightbox() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$selectedTab = $this->getVal('tab');
		$userId = $this->getVal('userId');
		$sessionUser = $this->wg->User;

		$tabs = array(
			array( 'id' => 'avatar', 'name' => 'Avatar' ),
			array( 'id' => 'about', 'name' => 'About Me' ),
			//array( 'id' => 'interview', 'name' => 'User Interview' ), //not yet --nAndy, 2011-06-15
		);

		$this->renderAvatarLightbox($userId);
		$this->renderAboutLightbox($userId);

		$this->setVal( 'tabs', $tabs );
		$this->setVal( 'selectedTab', $selectedTab );
		$this->setVal( 'isUserPageOwner', ( ( $userId == $sessionUser->getId() ) ? true : false ) );

		$this->setVal( 'wgBlankImgUrl', $this->wg->BlankImgUrl );

		$this->setVal( 'facebookPrefsLink', Skin::makeSpecialUrl('Preferences'));

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	public function saveInterviewAnswers() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = F::build('User', array($this->getVal('userId')), 'newFromId');
		$wikiId = $this->wg->CityId;

		$answers = json_decode( $this->getVal( 'answers' ) );

		$status = 'error';
		$errorMsg = $this->wf->msg( 'userprofilepage-interview-save-internal-error' );

		if( !$user->isAnon() && is_array( $answers ) ) {
			$this->profilePage = F::build( 'UserProfilePage', array( 'user' => $user) );

			if( !$this->profilePage->saveInterviewAnswers( $wikiId, $answers ) ) {
				$status = 'error';
				$errorMsg = $this->wf->msg( 'userprofilepage-interview-save-internal-error' );
			}
			else {
				$status = 'ok';
			}
		}

		$this->setVal( 'status', $status );
		if( $status == 'error' ) {
			$this->setVal( 'errorMsg', $errorMsg );
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Recives data from AJAX call, validates and saves new user data
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function saveUserData() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = F::build('User', array($this->getVal('userId')), 'newFromId');
		$isAllowed = ( $this->app->wg->User->isAllowed('editprofilev3') || intval($user->getId()) === intval($this->app->wg->User->getId()) );
		$userData = json_decode($this->getVal('data'));

		$status = 'error';
		$errorMsg = $this->wf->msg('user-identity-box-saving-internal-error');

		if( $isAllowed && is_object($userData) ) {
			$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));

			if( !empty($userData->website) && 0 !== strpos($userData->website, 'http') ) {
				$userData->website = 'http://'.$userData->website;
			}

			if( !$userIdentityBox->saveUserData($userData) ) {
				$status = 'error';
				$errorMsg = $this->wf->msg('userprofilepage-interview-save-internal-error');
			} else {
				$status = 'ok';
			}
		}

		if( $isAllowed && is_null($userData) ) {
			$errorMsg = $this->wf->msg('user-identity-box-saving-error');
		}

		$this->setVal('status', $status);
		if( $status === 'error' ) {
			$this->setVal('errorMsg', $errorMsg);
			return true;
		}

		if(!empty($userData->avatarData)) {
			$status = $this->saveUsersAvatar($user->getID(), $userData->avatarData);
			if($status !== true) {
				$this->setVal('errorMsg', $errorMsg);
				return true;
			}
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Validates and saves new user's avatar
	 *
	 * @param integer $userId id of user which avatar is going to be saved; taken from request if not given
	 * @param object $data data object with source of file and url/name of avatar's file; taken from request if not given
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function saveUsersAvatar($userId = null, $data = null) {
		$this->app->wf->ProfileIn( __METHOD__ );

		if( is_null($userId) ) {
			$user = F::build('User', array($this->getVal('userId')), 'newFromId');
		} else {
			$user = F::build('User', array($userId), 'newFromId');
		}

		$isAllowed = ( $this->app->wg->User->isAllowed('editprofilev3') || intval($user->getId()) === intval($this->app->wg->User->getId()) );

		if( is_null($data) ) {
			$data = json_decode($this->getVal('data'));
		}

		$errorMsg = $this->app->wf->msg('userprofilepage-interview-save-internal-error');
		$result = array('success' => true, 'error' => $errorMsg);

		if( $isAllowed && isset($data->source) && isset($data->file) ) {
			switch($data->source) {
				case 'sample':
					$user->setOption('avatar', $data->file);
					break;
				case 'facebook':
				case 'uploaded':
					$avatar = $this->saveAvatarFromUrl($user, $data->file, $errorMsg);
					$user->setOption('avatar', $avatar);
					break;
				default:
					$result = array('success' => false, 'error' => $errorMsg);
					$errorMsg = $this->app->wf->msg('userprofilepage-interview-save-internal-error');
					break;
			}
			$user->setOption('avatar_rev', date('U') );
			$user->saveSettings();
		}

		return true;
		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Gets avatar from url, saves it on server and resize it if needed then returns path
	 *
	 * @param User $user user object
	 * @param string $url url to user's facebook avatar
	 * @param string $errorMsg reference to a string variable where errors messages are returned
	 *
	 * @return string | boolean
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function saveAvatarFromUrl(User $user, $url, &$errorMsg) {
		$this->app->wf->ProfileIn( __METHOD__ );

		$userId = $user->getId();

		$errorNo = $this->uploadByUrl(
			$url,
			array(
				'userId' => $userId,
				'username' => $user->getName(),
				'user' => $user,
				'localPath' => '',
			),
			$errorMsg
		);

		$localPath = $this->getLocalPath($user);

		if ( $errorNo != UPLOAD_ERR_OK ) {
			$this->app->wf->ProfileOut( __METHOD__ );
			return false;
		} else {
			$this->app->wf->ProfileOut( __METHOD__ );
			return $localPath;
		}

		$this->app->wf->ProfileOut( __METHOD__ );
		return false;
	}

	/**
	 * @brief Submits avatar form and genarate url for preview
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onSubmitUsersAvatar() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$this->response->setContentType('text/html; charset=utf-8');

		$user = F::build('User', array($this->getVal('userId')), 'newFromId');

		$errorMsg = $this->wf->msg('userprofilepage-interview-save-internal-error');
		$result = array('success' => false, 'error' => $errorMsg);

		if( !$user->isAnon() && $this->request->wasPosted() ) {
			$avatarUploadFiled = 'UPPLightboxAvatar';
			$uploadError = $this->app->wg->request->getUploadError($avatarUploadFiled);

			if( $uploadError != 0) {
				$thumbnail =  $uploadError;
			} else {
				$fileName = $this->app->wg->request->getFileTempName($avatarUploadFiled);
				$fileuploader = new WikiaTempFilesUpload();

				$thumbnail = $this->storeInTempImage($fileName, $fileuploader);
			}

			if(is_int($thumbnail)) {
				$result = array('success' => false, 'error' => $this->validateUpload($thumbnail));
				$this->setVal('result', $result);
				return ;
			}

			$this->setVal('result', $result);

			$result = array('success' => true, 'avatar' => $thumbnail->url . '?cb=' . date('U') );
			$this->setVal('result', $result);

			$this->app->wf->ProfileOut( __METHOD__ );
			return;
		}

		$result = array('success' => false, 'error' => $errorMsg);
		$this->setVal('result', $result);
		return ;
	}

	protected function storeInTempImage($fileName, $fileuploader){
		$this->app->wf->ProfileIn( __METHOD__ );
		
		if(filesize($fileName) > self::AVATAR_MAX_SIZE ) {
			return UPLOAD_ERR_FORM_SIZE;
		}
		
		$tempName = $fileuploader->tempFileName($this->wg->User); 
		$title = Title::makeTitle(NS_FILE, $tempName);
		$localRepo = RepoGroup::singleton()->getLocalRepo();
		
		$ioh = F::build('ImageOperationsHelper');
		
		$out = $ioh->postProcessFile($fileName);
		if($out !== true) {
			return $out;
		}
		
		$file = new FakeLocalFile($title, $localRepo);
		$file->upload( $fileName , '', '' );

		$width = min(self::AVATAR_DEFAULT_SIZE, $file->width);
		$height = min(self::AVATAR_DEFAULT_SIZE, $file->height);

		$thumbnail = $file->transform(array(
			'height' => $height,
			'width' => $width,
		));
		
		$this->app->wf->ProfileOut( __METHOD__ );
		return $thumbnail;
	}
	/**
	 * @brief Validates file upload (whenever it's regular upload or by-url upload) and sets status and errorMsg
	 *
	 * @param integer $errorNo variable being checked
	 * @param string $status status depends on value of $errorNo can be returned as 'error' or 'success'
	 * @param string $errorMsg error message for humans
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function validateUpload($errorNo ) {
		switch( $errorNo ) {
			case UPLOAD_ERR_NO_FILE:
				return $this->wf->msg('user-identity-box-avatar-error-nofile');
				break;

			case UPLOAD_ERR_CANT_WRITE:
				return $this->wf->msg('user-identity-box-avatar-error-cantwrite');
				break;

			case UPLOAD_ERR_FORM_SIZE:
				return $this->wf->msg('user-identity-box-avatar-error-size', (int)(self::AVATAR_MAX_SIZE/1024));
				break;
			case UPLOAD_ERR_EXTENSION;
				return wfMsg('userprofilepage-avatar-error-type', $this->wg->Lang->listToText( ImageOperationsHelper::getAllowedMime() ) );
				break;
			case ImageOperationsHelper::UPLOAD_ERR_RESOLUTION:
				return $this->wf->msg('userprofilepage-avatar-error-resolution');
				break;
			default:
				return  wfMsg('user-identity-box-avatar-error');
		}
	}

	/**
	 * @brief get Local Path to avatar
	 *
	 */

	private function getLocalPath($user) {
		$oAvatarObj = F::build('Masthead', array( $user ), 'newFromUser');
		return $oAvatarObj->getLocalPath();
		return '';
	}

	/**
	 * @brief Saves the file on the server
	 *
	 * @param Request $request    WebRequest instance
	 * @param array $userData     user data array; contains: user id (key: userId), full page url (fullPageUrl), user name (username)
	 * @param String $input       name of file input in form
	 * @param $errorMsg           optional string containing details on what went wrong if there is an UPLOAD_ERR_EXTENSION
	 *
	 * @return Integer an error code of operation
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function uploadFile($request, $userData, $input, &$errorMsg='') {
		$this->app->wf->ProfileIn(__METHOD__);

		$errorNo = $this->wg->request->getUploadError( $input );

		if ( $errorNo != UPLOAD_ERR_OK ) {
			$this->app->wf->ProfileOut(__METHOD__);
			return $errorNo;
		}

		$errorMsg = "";

		if(class_exists('Masthead')) {
			$oAvatarObj = F::build('Masthead', array( $userData['user'] ), 'newFromUser');
			$errorNo = $oAvatarObj->uploadFile( $this->wg->request, 'UPPLightboxAvatar', $errorMsg );


		} else {
			$errorNo = UPLOAD_ERR_EXTENSION;
		}

		$this->app->wf->ProfileOut(__METHOD__);
		return $errorNo;
	}

	/**
	 * @desc While this is technically downloading the URL, the function's purpose is to be similar
	 * to uploadFile, but instead of having the file come from the user's computer, it comes
	 * from the supplied URL.
	 *
	 * @param String $url        the full URL of an image to download and apply as the user's Avatar i.e. user's facebook avatar
	 * @param array $userData    user data array; contains: user id (key: userId), full page url (fullPageUrl), user name (username)
	 * @param $errorMsg          optional string containing details on what went wrong if there is an UPLOAD_ERR_EXTENSION
	 *
	 * @return Integer error code of operation
	 */
	public function uploadByUrl($url, $userData, &$errorMsg='') {
		$this->app->wf->ProfileIn(__METHOD__);
		//start by presuming there is no error
		$errorNo = UPLOAD_ERR_OK;
		$user = $userData['user'];
		if( class_exists('Masthead') ) {
			$oAvatarObj = F::build('Masthead', array( $user), 'newFromUser');
			$oAvatarObj->purgeUrl();
			$localPath = $this->getLocalPath($user);
			$errorNo = $oAvatarObj->uploadByUrl($url);
			$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
			$userData = $userIdentityBox->setData();
			$userData['avatar'] = $localPath;
			$userIdentityBox->saveUserData($userData);
		} else {
			$errorNo = UPLOAD_ERR_EXTENSION;
		}

		$this->app->wf->ProfileOut(__METHOD__);
		return $errorNo;
	}

	/**
	 * @brief Passes more data to the template to render avatar modal box
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function renderAvatarLightbox($userId) {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = F::build('User', array($userId), 'newFromId');

		$this->setVal( 'defaultAvatars', $this->getDefaultAvatars() );
		$this->setVal( 'isUploadsPossible', $this->wg->EnableUploads && $this->wg->User->isAllowed( 'upload' ) && is_writeable( $this->wg->UploadDirectory ) );

		$this->setVal( 'avatarName', $user->getOption('avatar') );
		$this->setVal( 'userId', $userId );
		$this->setVal( 'avatarMaxSize', self::AVATAR_MAX_SIZE );
		$this->setVal( 'avatar', F::build( 'AvatarService', array( $user->getName(), self::AVATAR_DEFAULT_SIZE ), 'renderAvatar' ) );
		$this->setVal( 'fbAvatarConnectButton', '<fb:login-button perms="user_about_me" onlogin="UserProfilePage.fbConnectAvatar();">'.$this->app->wf->Msg('user-identity-box-connect-to-fb').'</fb:login-button>' );

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Gets an array of sample avatars
	 *
	 * @desc Method based on Masthead::getDefaultAvatars()
	 *
	 * @param string $thumb a thumb
	 *
	 * @return array multidimensional array with default avatars defined on messaging.wikia.com
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function getDefaultAvatars($thumb = '') {
		$this->app->wf->ProfileIn( __METHOD__ );

		//parse message only once per request
		if( empty($thumb) && is_array($this->defaultAvatars) && count($this->defaultAvatars) > 0 ) {
			$this->app->wf->ProfileOut( __METHOD__ );
			return $this->defaultAvatars;
		}

		$this->defaultAvatars = array();
		$images = $this->app->runFunction('getMessageAsArray', 'blog-avatar-defaults');

		if( is_array($images) ) {
			foreach( $images as $image ) {
				$hash = F::build('FileRepo', array($image, 2), 'getHashPathForLevel');
				$this->defaultAvatars[] = array('name' => $image, 'url' => $this->defaultAvatarPath.$thumb.$hash.$image);
			}
		}

		$this->app->wf->ProfileOut( __METHOD__ );
		return $this->defaultAvatars;
	}

	/**
	 * @brief Passes more data to the template to render about modal box
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function renderAboutLightbox($userId) {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = F::build('User', array($userId), 'newFromId');

		$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
		$userData = $userIdentityBox->setData(true);

		$this->setVal('user', $userData);
		$this->setVal('months', $this->getMonths());
		$this->setVal( 'fbConnectButton', '<fb:login-button perms="user_about_me,user_birthday,user_location,user_work_history,user_website" onlogin="UserProfilePage.fbConnect();">'.$this->app->wf->Msg('user-identity-box-connect-to-fb').'</fb:login-button>' );

		if( !empty($userData['birthday']['month']) ) {
			$this->setVal('days', cal_days_in_month( CAL_GREGORIAN, $userData['birthday']['month'], 2000 /* leap year */ ) );
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}
			
	/**
	 * @brief Get user object from given title
	 * 
	 * @desc getUserFromTitle() is sometimes called in hooks therefore I added returnUser flag and when
	 * it is set to true getUserFromTitle() will assign $this->user variable with a user object
	 * 
	 * @requestParam Title $title title object
	 * @requestParam Boolean $returnUser a flag set to true or false
	 * 
	 * @return User
	 *
	 * @author ADi
	 * @author nAndy
	 */
	public function getUserFromTitle() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$title = $this->getVal('title');
		$returnUserInData = (boolean) $this->getVal('returnUser');

		if( !empty($title) && is_string($title) && strpos($title, ':') !== false ) {
			$title = F::build('Title', array($title), 'newFromText');
		} else {
			$title = $this->app->wg->Title;
		}

		$title = $this->getVal('title');
		$returnUserInData = (boolean) $this->getVal('returnUser');
		
		if( !empty($title) && is_string($title) && strpos($title, ':') !== false ) {
			$title = F::build('Title', array($title), 'newFromText');
		}
		
		$user = null;
		
		if( $title instanceof Title && in_array( $title->getNamespace(), $this->allowedNamespaces ) ) {
			// get "owner" of this user / user talk / blog page
			$parts = explode('/', $title->getText());
		} else if( $title instanceof Title && $title->getNamespace() == NS_SPECIAL && ($title->isSpecial('Following') || $title->isSpecial('Contributions')) ) {
			$target = $this->getVal('target');
			$target = ( empty($target) ) ? $this->app->wg->Request->getVal('target') : $target;

			if( !empty($target) ) {
				// Special:Contributions?target=FooBar (RT #68323)
				$parts = array($target);
			} else {
				// get user this special page referrs to
				$titleVal = $this->app->wg->Request->getVal('title', false);
				$parts = explode('/', $titleVal);

				// remove special page name
				array_shift($parts);
			}

			if( $title->isSpecial('Following') && !isset($parts[0]) ) {
			//following pages are rendered only for profile owners
				return $this->app->wg->User;
			}
		}
		
		if( !empty($parts[0]) ) {
			$userName = str_replace('_', ' ', $parts[0]);
			$user = F::build('User', array($userName), 'newFromName');
		}

		if( !($user instanceof User) && !empty($userName) ) {
		//it should work only for title=User:AAA.BBB.CCC.DDD where AAA.BBB.CCC.DDD is an IP address
		//in previous user profile pages when IP was passed it returned false which leads to load
		//"default" oasis data to Masthead; here it couldn't be done because of new User Identity Box
			$user = F::build('User');
			$user->mName = $userName;
			$user->mFrom = 'name';
		}

		if( !($user instanceof User) && empty($userName) ) {
		//this is in case Blog:Recent_posts or Special:Contribution will be called
		//then in title there is no username and "default" user instance is $wgUser
			$user = $this->app->wg->User;
		}

		if( $returnUserInData ) {
			$this->user = $user;
		}

		if( $returnUserInData ) {
			$this->user = $user;
		}
		
		$this->app->wf->ProfileOut( __METHOD__ );
		return $user;
	}

	/**
	 * @brief hook handler
	 */
	public function onSkinTemplateOutputPageBeforeExec( $skin, $template ) {
		$this->app->wf->ProfileIn( __METHOD__ );

		$this->setRequest( new WikiaRequest( $this->app->wg->Request->getValues() ) );

		$title = $this->app->wg->Title;
		$user = $this->getUserFromTitle();
		
		if( $user instanceof User ) {
			$response = $this->app->sendRequest(
			  'UserProfilePage',
			  'index',
			  array(
			    'user' => $user,
			    'userPageBody' => $template->data['bodytext'],
			    'wikiId' => $this->app->wg->CityId,
			  ));
			
			$template->data['bodytext'] = (string) $response;
		}

		$this->app->wf->ProfileOut( __METHOD__ );
		return true;
	}

	/**
	 *
	 * @brief create preview for avatar from FB
	 *
	 * @author Tomasz Odrobny
	 */

	public function onFacebookConnectAvatar() {
		$user = $this->app->wg->User;

		$result = array('success' => false, 'error' => $this->wf->Msg('userprofilepage-interview-save-internal-error'));
		$this->setVal('result', $result);

		if( !$user->isAnon() ) {
			$fbConnectAPI  = F::build('FBConnectAPI');
			$fbUserId = $fbConnectAPI->user();

			$userFbData = $fbConnectAPI->getUserInfo(
				$fbUserId,
				array('pic_big')
			);

			$data->source = 'facebook';
			$data->file = $userFbData['pic_big'];

			$oAvatarObj = F::build('Masthead', array( $user ), 'newFromUser');
			$tmpFile = '';
			$oAvatarObj->uploadByUrlToTempFile($data->file, $tmpFile);

			$fileuploader = new WikiaTempFilesUpload();
			$thumbnail = $this->storeInTempImage($tmpFile, $fileuploader);

			$result = array('success' => true, 'avatar' => $thumbnail->url . '?cb=' . date('U') );
			$this->setVal('result', $result);
		}

		$this->app->wf->ProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * @brief Gets facebook user data from database or tries to connect via FB API and get those data then returns it as a JSON data
	 *
	 * @desc Checks if user is logged-in only because we decided to put facebook connect button only for owners of a profile; staff can not see the button
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onFacebookConnect() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$user = $this->app->wg->User;
		$result = array('success' => false);

		if( !$user->isAnon() ) {
			$fb_ids = F::build('FBConnectDB', array($user), 'getFacebookIDs');
			$fbConnectAPI  = F::build('FBConnectAPI');

			if( count($fb_ids) > 0 ) {
				$fbUserId = $fb_ids[0];
			} else {
				$fbUserId = $fbConnectAPI->user();
			}

			if( $fbUserId > 0 ) {
				$userFbData = $fbConnectAPI->getUserInfo(
					$fbUserId,
					array('first_name, current_location, hometown_location, work_history, profile_url, sex, birthday_date, pic_big, website')
				);
				$userFbData = $this->cleanFbData($userFbData);
				$result = array('success' => true, 'fbUser' => $userFbData);
			} else {
				$result = array('success' => false, 'error' => $this->app->wf->Msg('user-identity-box-invalid-fb-id-error'));
			}
		} else {
			$result = array('success' => false, 'error' => $this->wf->Msg('userprofilepage-interview-save-internal-error'));
		}

		$this->setVal('result', $result);

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief Clears all data recivied from Facebook so all of them are strings
	 *
	 * @param array $fbData facebook user data recivied from FBConnectAPI::getUserInfo()
	 *
	 * @return array
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function cleanFbData($fbData) {
		unset($fbData['uid']);

		foreach($fbData as $key => $data) {
			if( !is_string($data) ) {
				switch($key) {
					case 'work_history':
						$fbData['work_history'] = $this->extractFbFirstField($fbData['work_history'], 'position');
						break;
					case 'current_location':
						$fbData['current_location'] = $this->extractFbFirstField($fbData['current_location'], 'city');
						break;
				}
			}
		}

		if( !empty($fbData['website']) ) {
			$websites = nl2br($fbData['website']);
			$websites = explode('<br />', $websites);
			$fbData['website'] = ( isset($websites[0]) ? $websites[0] : '');
		}

		if( empty($fbData['current_location']) ) {
			$this->extractFbFirstField($fbData['hometown_location'], 'city');
		} else {
			unset($fbData['hometown_location']);
		}

		if( !empty($fbData['first_name']) ) {
			$fbData['name'] = $fbData['first_name'];
			unset($fbData['first_name']);
		}

		return $fbData;
	}

	/**
	 * @brief Searches for a string data to return
	 *
	 * @param array | string    $fbData data from facebook
	 * @param string            $field of an array which we want to find and return
	 *
	 * @return string
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	private function extractFbFirstField($fbData, $field = null) {
		if( is_null($field) ) {
			return '';
		}

		if( !empty($fbData[$field]) && is_string($fbData[$field]) ) {
			return $fbData[$field];
		}

		if( !empty($fbData[0]) && !empty($fbData[0][$field]) ) {
			return $fbData[0][$field];
		}

		return '';
	}

	/**
	 * @brief Gets wikiId from request and hides from faviorites wikis
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onHideWiki() {
		$result = array('success' => false);
		$userId = intval( $this->getVal('userId') );
		$wikiId = intval( $this->getVal('wikiId') );

		$user = F::build('User', array($userId), 'newFromId');
		$isAllowed = ( $this->app->wg->User->isAllowed('editprofilev3') || intval($user->getId()) === intval($this->app->wg->User->getId()) );

		if( $isAllowed && $wikiId > 0 ) {
			$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
			$success = $userIdentityBox->hideWiki($wikiId);

			$result = array( 'success' => $success, 'wikis' => $userIdentityBox->getTopWikis() );
		}

		$this->setVal('result', $result);
	}

	/**
	 * @brief Gets fav wikis information and passes it as JSON
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onRefreshFavWikis() {
		$result = array('success' => false);
		$userId = intval( $this->getVal('userId') );

		$user = F::build('User', array($userId), 'newFromId');

		$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
		$result = array( 'success' => true, 'wikis' => $userIdentityBox->getTopWikis(true) );

		$this->setVal('result', $result);
	}

	public function getClosingModal() {
		$this->app->wf->ProfileIn( __METHOD__ );

		$userId = $this->getVal('userId');
		$user = $this->wg->User;

		if( !$user->isAnon() ) {
			$this->setVal( 'body', (string) $this->sendSelfRequest('renderClosingModal', array('userId' => $userId)) );
		} else {
			throw new WikiaException( 'User not logged in' );
		}

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	public function renderClosingModal() {
		$this->app->wf->ProfileIn( __METHOD__ );

		//we want only the template for now...

		$this->app->wf->ProfileOut( __METHOD__ );
	}

	/**
	 * @brief remove User:: from back link
	 *
	 * @author Tomek Odrobny
	 */

	public function onSkinSubPageSubtitleAfterTitle($title, &$ptext) {
		if( !empty($title) && $title->getNamespace() == NS_USER) {
			$ptext = $title->getText();	
		}
		return true;
	}

	/**
	 * @brief adds wiki id to cache and fav wikis instantly
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onArticleSaveComplete(&$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision, &$status, $baseRevId) {
		$wikiId = intval( $this->app->wg->CityId );

		if( $user instanceof User && $wikiId > 0 ) {
			$userIdentityBox = F::build('UserIdentityBox', array($this->app, $user, self::MAX_TOP_WIKIS));
			$userIdentityBox->addTopWiki($wikiId);
		}

		return true;
	}

}
