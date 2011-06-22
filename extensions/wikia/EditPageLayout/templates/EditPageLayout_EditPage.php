<?= $header ?>
<section id="WikiaPage" class="WikiaPage">
	<article id="WikiaMainContent" class="WikiaMainContent" style="float:none">
		<header id="EditPageHeader">
			<span class="wordmark <?= $wordmark['wordmarkSize'] ?> <?= $wordmark['wordmarkType'] ?> font-<?= $wordmark['wordmarkFont'] ?>" <?= $wordmark['wordmarkStyle'] ?>>
				<a accesskey="z" href="<?= htmlspecialchars($wordmark['mainPageURL']) ?>">
				<? if (!empty($wordmark['wordmarkUrl'])) { ?>
					<img src="<?= $wordmark['wordmarkUrl'] ?>" alt="<?= htmlspecialchars($wordmark['wordmarkText']) ?>">
				<? } else { ?>
					<?= htmlspecialchars($wordmark['wordmarkText']) ?>
				<? } ?>
				</a>
			</span>
			<!-- "Editing" -->
			<h2>
				<?= $editing ?>
			</h2>
			<!-- edited page title -->
			<h1>
				<a href="<?= htmlspecialchars($title->getLocalUrl()) ?>" title="<?= htmlspecialchars($titleText) ?>"><?= $titleShortText ?></a>
			</h1>
			<!-- pencil icon -->
			<a id="EditPageTitle" title="<?= wfMsg('editpagelayout-edit-title') ?>">
				<img class="sprite edit-pencil-small" src="<?= $wgBlankImgUrl ?>">
			</a>
			<!-- mode switching tabs -->
			<nav id="EditPageTabs" class="editpage-tabs" data-space-type="tabs" data-space-autoshow="true" style="display:none"></nav>
			<aside id="HelpLink"><?= $helpLink ?></aside>
		</header>

		<form id="editform" name="editform" method="post" action="<?= htmlspecialchars($editFormAction) ?>" enctype="multipart/form-data">
			<section id="EditPage">
				<div id="EditPageMain">
					<div id="EditPageToolbar" class="editpage-toolbar" data-space-type="toolbar"></div>
					<div id="EditPageEditorWrapper" data-space-type="editor">
						<div id="EditPageEditor" class="editpage-content">

							<!-- notices -->
							<noscript><style>
								.edit-page-notices {
									display: block;
								}
							</style></noscript>
							<div class="editpage-notices" data-space-type="notices-short">
								<ul>
<?php
	foreach ($notices as $notice) {
?>
									<li><?= $notice ?></li>
<?php
	}
?>
								</ul>
								<span class="dismiss-icon sprite-small close"></span>
							</div>
							<div class="editpage-notices-html" data-space-type="notices-html" style="display:none"><?= $noticesHtml?></div>

							<!-- preloads -->
							<?php
								if (!empty($editPagePreloads)) {
									
									foreach($editPagePreloads as $preloadId => $preload) {
							?>
							<div id="<?= $preloadId ?>" class="editpage-intro">
								<div class="editpage-intro-wrapper">
									<div class="<?= $preload['class'] ?>">
										<?= $preload['content'] ?>
									</div>
								</div>
								<a class="expand">
									<label><?= wfMsg('editpagelayout-more') ?></label>
									<span>+</span>
								</a>
							</div>
							<div class="gap">&nbsp;</div>
							<?php
									}
								}
							?>

							<!-- edit form content -->
							<?= $bodytext ?>
							<div class="editpage-loading-indicator" data-space-type="loading-status" style="display:none">
								<div class="loading-background"></div>
								<div class="loading-message">
									<span class="loading-throbber">&nbsp;</span>
									<span class="loading-text">&nbsp;</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Collapse bar for widescreen source mode -->
				<div class="editpage-widemode-trigger" style="display: none">
					<span class="arrow"></span>
				</div>
				<div id="EditPageRail" class="editpage-rail" data-space-type="rail">
					<!-- Page Controls module -->
					<noscript><style>
						.module_page_controls .wikia-menu-button {
							display: none;
						}
					</style></noscript>
					<div class="module module_page_controls">
						<div class="module_content">
							<div class="checkboxes">
								<?php
									foreach($customCheckboxes as $checkbox) {
								?>
								<label class="<?= $checkbox['name'] ?>">
									<input type="checkbox" name="<?= $checkbox['name'] ?>" id="<?= $checkbox['name'] ?>" <?= $checkbox['checked'] ? ' checked="checked"' : '' ?> />
									<?= $checkbox['label'] ?>
								</label>
								<?php
									}
								?>
								<label class="wpMinoredit">
									<input type="checkbox" name="wpMinoredit" id="wpMinoredit" accesskey="<?=wfMsg('accesskey-minoredit');?>"<?= $minorEditCheckbox ? ' checked="checked"' : '' ?> />
									<?= wfMsg('editpagelayout-pageControls-minorEdit') ?>
								</label>
							</div>
							<div id="wpSummaryLabel">
								<?= $summaryBox ?>
							</div>
							<nav class="buttons">
<?php
	foreach ($buttons as $button) {
	 	$buttonType = !empty($button['type']) ? $button['type'] : 'button';
		$buttonClasses = 'control-button'
			. ($button['seqNo'] % 2 == 1 ? ' even' : '')
			. (!$button['enabled'] ? ' disabled' : '')
			. (!empty($button['className']) ? ' ' . $button['className'] : '');
	 	switch ($buttonType) {
	 		case 'save':
?>
								<input class="<?=$buttonClasses?>" id="wpSave" name="wpSave" type="submit" value="<?= wfMsg('savearticle') ?>" accesskey="<?=wfMsg('accesskey-save');?>" />
<?php
	 			break;
	 		case 'preview':
?>
								<ul class="wikia-menu-button secondary <?=$buttonClasses?>">
									<li>
										<a id="wpPreview" accesskey="<?=wfMsg('accesskey-preview');?>"><?= wfMsg('preview') ?></a>
										<img src="<?= $wgBlankImgUrl ?>" class="chevron">
									</li>
									<ul>
										<li>
											<a id="wpDiff" accesskey="<?=wfMsg('accesskey-diff');?>"><?= wfMsg('showdiff') ?></a>
										</li>
									</ul>
								</ul>
<?php
	 			break;
	 		default:
?>
								<input class="<?=$buttonClasses?>"  id="<?=$button['name']?>" name="<?=$button['name']?>" type="<?=$buttonType?>" value="<?=$button['caption']?>" />
<?php
	 	}
	}
?>
							</nav>
						</div>
					</div>
				</div>
			</section>
		</form>
	</article>
</section>
