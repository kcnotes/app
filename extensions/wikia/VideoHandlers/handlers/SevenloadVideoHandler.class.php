<?php

class SevenloadVideoHandler extends VideoHandler {
	protected $apiName = 'SevenloadApiWrapper';
	protected static $aspectRatio = 1.59235669;	// 500 x 314
	protected static $urlTemplate = 'http://en.sevenload.com/pl/$1/$2x$3/swf$4';
	
	public function getEmbed($articleId, $width, $autoplay = false, $isAjax = false, $postOnload=false) {
		$height = $this->getHeight($width);
		$url = str_replace('$1', $this->getEmbedVideoId(), static::$urlTemplate);
		$url = str_replace('$2', $width, $url);
		$url = str_replace('$3', $height, $url);
		$url = str_replace('$4', $autoplay ? '/play' : '', $url);

		$html = <<<EOT
<object type="application/x-shockwave-flash" data="$url" width="$width" height="$height">
	<param name="allowFullscreen" value="true" />
	<param name="allowScriptAccess" value="always" />
	<param name="movie" value="$url" />
</object>       
EOT;
		
		return $html;
	}
	
}