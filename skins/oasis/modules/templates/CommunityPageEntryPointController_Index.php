<section class="community-page-entry-point-module">
	<div class="avatars">
		<? foreach( $avatars as $index => $avatar ): ?>
			<a href="<?= $avatar['userProfileUrl'] ?>">
				<img class="wds-avatar" style="position: absolute; left:<?= $index * 20 ?>px; z-index: <?= 5 - $index ?>" src="<?= $avatar['avatarUrl'] ?>" />
			</a>
		<? endforeach; ?>
	</div>
	<div class="content">
		<div class="description"><?= wfMessage( 'communitypage-help-us-grow' )->parse() ?></div>
		<a href="<?= SpecialPage::getTitleFor( 'Community' )->getLocalURL(); ?>" class="wds-is-secondary wds-button wds-is-squished"><?= wfMessage( 'communitypage-entry-button' )->escaped() ?></a>
	</div>
</section>
