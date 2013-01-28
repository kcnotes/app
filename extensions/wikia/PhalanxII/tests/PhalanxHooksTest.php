<?php

class PhalanxHooksTest extends WikiaBaseTest {

	/**
	 * setup tests
	 */
	public function setUp() {

		$this->setupFile =  dirname(__FILE__) . '/../Phalanx_setup.php';
		wfDebug( __METHOD__ . ': '  .$this->setupFile );

		parent::setUp();
	}

	public function testPhalanxUserBlock() {
		$serviceMock = $this->getMock( "PhalanxService", array( "match" ), array(), "", false );
		$serviceMock->expects( $this->any() )
			->method( "match" )
			->will( $this->returnValue( 0 ) ); // no match

	}
}
