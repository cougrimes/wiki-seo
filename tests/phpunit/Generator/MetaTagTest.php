<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator;

use MediaWiki\Extension\WikiSEO\Generator\MetaTag;

class MetaTagTest extends GeneratorTest {
	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addMetadata
	 */
	public function testAddMetadata() {
		$metadata = [
			'description' => 'Example Description',
			'keywords'    => 'Keyword 1, Keyword 2',
		];

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertContains( [ 'description', 'Example Description' ], $out->getMetaTags() );
		$this->assertContains( [ 'keywords', 'Keyword 1, Keyword 2' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addGoogleSiteVerification
	 */
	public function testAddGoogleSiteKey() {
		$this->setMwGlobals( 'wgGoogleSiteVerificationKey', 'google-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'google-site-verification', 'google-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addNortonSiteVerification
	 */
	public function testAddNortonSiteVerification() {
		$this->setMwGlobals( 'wgNortonSiteVerificationKey', 'norton-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [
			'norton-safeweb-site-verification',
			'norton-key',
		], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addPinterestSiteVerification
	 */
	public function testAddPinterestSiteVerification() {
		$this->setMwGlobals( 'wgPinterestSiteVerificationKey', 'pinterest-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'p:domain_verify', 'pinterest-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addAlexaSiteVerification
	 */
	public function testAddAlexaSiteVerification() {
		$this->setMwGlobals( 'wgAlexaSiteVerificationKey', 'alexa-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'alexaVerifyID', 'alexa-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addYandexSiteVerification
	 */
	public function testAddYandexSiteVerification() {
		$this->setMwGlobals( 'wgYandexSiteVerificationKey', 'yandex-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'yandex-verification', 'yandex-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addBingSiteVerification
	 */
	public function testAddBingSiteVerification() {
		$this->setMwGlobals( 'wgBingSiteVerificationKey', 'bing-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'msvalidate.01', 'bing-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addFacebookAppId
	 */
	public function testAddFacebookAppId() {
		$this->setMwGlobals( 'wgFacebookAppId', '0011223344' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'fb:app_id', $out->getHeadItemsArray() );
		$this->assertEquals( '<meta property="fb:app_id" content="0011223344"/>',
			$out->getHeadItemsArray()['fb:app_id'] );
	}
}
