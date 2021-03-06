<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikiSEO;

use MediaWiki\Extension\WikiSEO\Generator\GeneratorInterface;
use MediaWiki\Extension\WikiSEO\Generator\MetaTag;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Parser;
use ParserOutput;
use PPFrame;
use ReflectionClass;
use ReflectionException;
use WebRequest;

class WikiSEO {
	private const MODE_TAG = 'tag';
	private const MODE_PARSER = 'parser';
	private const PAGE_PROP_NAME = 'WikiSEO';

	/**
	 * @var string $mode 'tag' or 'parser' used to determine the error message
	 */
	private $mode;

	/**
	 * prepend, append or replace the new title to the existing title
	 *
	 * @var string
	 */
	private $titleMode = 'replace';

	/**
	 * the separator to use when using append or prepend modes
	 *
	 * @var string
	 */
	private $titleSeparator = ' - ';

	/**
	 * @var string[] Array with generator names
	 */
	private $generators;

	/**
	 * @var GeneratorInterface[]
	 */
	private $generatorInstances = [];

	/**
	 * @var string[] Possible error messages
	 */
	private $errors = [];

	/**
	 * @var array
	 */
	private $metadata = [];

	/**
	 * WikiSEO constructor.
	 * Loads generator names from LocalSettings
	 *
	 * @param string $mode the parser mode
	 */
	public function __construct( $mode = self::MODE_PARSER ) {
		global $wgMetadataGenerators;

		$this->generators = $wgMetadataGenerators;

		$this->mode = $mode;
	}

	/**
	 * Set the metadata by loading the page props from the db
	 *
	 * @param OutputPage $outputPage
	 */
	public function setMetadataFromPageProps( OutputPage $outputPage ) {
		if ( $outputPage->getTitle() === null ) {
			$this->errors[] = wfMessage( 'wiki-seo-missing-page-title' );

			return;
		}

		$pageId = $outputPage->getTitle()->getArticleID();

		$dbl = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$db = $dbl->getConnection( DB_REPLICA );

		$propValue = $db->selectField( 'page_props', 'pp_value', [
			'pp_page' => $pageId,
			'pp_propname' => self::PAGE_PROP_NAME,
		], __METHOD__ );

		// Not found in DB, let's try OutputPage
		if ( $propValue === false ) {
			$propValue = $outputPage->getProperty( self::PAGE_PROP_NAME ) ?? '{}';
		}

		$this->setMetadata( json_decode( $propValue, true ) );
	}

	/**
	 * Add the metadata array as meta tags to the page
	 *
	 * @param OutputPage $out
	 */
	public function addMetadataToPage( OutputPage $out ) {
		$this->modifyPageTitle( $out );
		$this->instantiateMetadataPlugins();

		foreach ( $this->generatorInstances as $generatorInstance ) {
			$generatorInstance->init( $this->metadata, $out );
			$generatorInstance->addMetadata();
		}
	}

	/**
	 * Set an array with metadata key value pairs
	 * Gets validated by Validator
	 *
	 * @param array $metadataArray
	 * @see Validator
	 */
	private function setMetadata( array $metadataArray ) {
		$validator = new Validator();
		$validMetadata = [];

		foreach ( $validator->validateParams( $metadataArray ) as $k => $v ) {
			if ( !empty( $v ) ) {
				$validMetadata[$k] = $v;
			}
		}

		$this->metadata = $validMetadata;
	}

	/**
	 * Instantiates the metadata generators from $wgMetadataGenerators
	 */
	private function instantiateMetadataPlugins() {
		$this->generatorInstances[] = new MetaTag();

		foreach ( $this->generators as $generator ) {
			$classPath = "MediaWiki\\Extension\\WikiSEO\\Generator\\Plugins\\$generator";

			try {
				$class = new ReflectionClass( $classPath );
				$this->generatorInstances[] = $class->newInstance();
			} catch ( ReflectionException $e ) {
				$this->errors[] = wfMessage( 'wiki-seo-invalid-generator', $generator )->parse();
			}
		}
	}

	/**
	 * Finalize everything.
	 * Check for errors and save to props if everything is ok.
	 *
	 * @param ParserOutput $output
	 *
	 * @return string String with errors that happened or empty
	 */
	private function finalize( ParserOutput $output ) {
		if ( empty( $this->metadata ) ) {
			$message = sprintf( 'wiki-seo-empty-attr-%s', $this->mode );
			$this->errors[] = wfMessage( $message );

			return $this->makeErrorHtml();
		}

		$this->saveMetadataToProps( $output );

		return '';
	}

	/**
	 * @return string Concatenated error strings
	 */
	private function makeErrorHtml() {
		$text = implode( '<br>', $this->errors );

		return sprintf( '<div class="errorbox">%s</div>', $text );
	}

	/**
	 * Modifies the page title based on 'titleMode'
	 *
	 * @param OutputPage $out
	 */
	private function modifyPageTitle( OutputPage $out ) {
		if ( !array_key_exists( 'title', $this->metadata ) ) {
			return;
		}

		$metaTitle = $this->metadata['title'];

		if ( array_key_exists( 'title_separator', $this->metadata ) ) {
			$this->titleSeparator = html_entity_decode( $this->metadata['title_separator'] );
		}

		if ( array_key_exists( 'title_mode', $this->metadata ) ) {
			$this->titleMode = $this->metadata['title_mode'];
		}

		switch ( $this->titleMode ) {
			case 'append':
				$pageTitle = sprintf( '%s%s%s', $out->getPageTitle(), $this->titleSeparator, $metaTitle );
				break;
			case 'prepend':
				$pageTitle = sprintf( '%s%s%s', $metaTitle, $this->titleSeparator, $out->getPageTitle() );
				break;
			case 'replace':
			default:
				$pageTitle = $metaTitle;
		}

		$pageTitle = preg_replace( "/\r|\n/", '', $pageTitle );

		$out->setHTMLTitle( $pageTitle );
	}

	/**
	 * Save the metadata array json encoded to the page props table
	 *
	 * @param ParserOutput $outputPage
	 */
	private function saveMetadataToProps( ParserOutput $outputPage ) {
		$outputPage->setProperty( self::PAGE_PROP_NAME, json_encode( $this->metadata ) );
	}

	/**
	 * Parse the values input from the <seo> tag extension
	 *
	 * @param string $input The text content of the tag
	 * @param array $args The HTML attributes of the tag
	 * @param Parser $parser The active Parser instance
	 * @param PPFrame $frame
	 *
	 * @return string The HTML comments of cached attributes
	 */
	public static function fromTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		$seo = new WikiSEO( self::MODE_TAG );
		$tagParser = new TagParser();

		$parsedInput = $tagParser->parseText( $input );
		$tags = $tagParser->expandWikiTextTagArray( $parsedInput, $parser, $frame );
		$tags = array_merge( $tags, $args );

		$seo->setMetadata( $tags );

		return $seo->finalize( $parser->getOutput() );
	}

	/**
	 * Parse the values input from the {{#seo}} parser function
	 *
	 * @param Parser $parser The active Parser instance
	 * @param PPFrame $frame Frame
	 * @param array $args Arguments
	 *
	 * @return array Parser options and the HTML comments of cached attributes
	 */
	public static function fromParserFunction( Parser $parser, PPFrame $frame, array $args ) {
		$expandedArgs = [];

		foreach ( $args as $arg ) {
			$expandedArgs[] = trim( $frame->expand( $arg ) );
		}

		$seo = new WikiSEO( self::MODE_PARSER );
		$tagParser = new TagParser();

		$seo->setMetadata( $tagParser->parseArgs( $expandedArgs ) );

		$fin = $seo->finalize( $parser->getOutput() );
		if ( !empty( $fin ) ) {
			return [
				$fin,
				'noparse' => true,
				'isHTML' => true,
			];
		}

		return [];
	}

	/**
	 * Add the server protocol to the URL if it is missing
	 *
	 * @param string $url URL from getFullURL()
	 * @param WebRequest $request
	 *
	 * @return string
	 */
	public static function protocolizeUrl( $url, WebRequest $request ) {
		if ( parse_url( $url, PHP_URL_SCHEME ) === null ) {
			$url = sprintf( '%s:%s', $request->getProtocol(), $url );
		}

		return $url;
	}
}
