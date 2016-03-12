<?php

namespace SMW\Exporter;

use SMW\Exporter\Element\ExpResource;
use SMW\Exporter\Element\ExpElement;
use SMW\Exporter\Escaper;
use SMW\DIWikiPage;
use SMW\Store;
use SMW\Localizer;
use SMWExporter as Exporter;
use SMWDataItem as DataItem;
use Title;

/**
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class DataItemByExpElementMatchFinder {

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var string
	 */
	private $wikiNamespace;

	/**
	 * @since 2.4
	 *
	 * @param Store $store
	 * @param string $wikiNamespace
	 */
	public function __construct( Store $store, $wikiNamespace = '' ) {
		$this->store = $store;
		$this->wikiNamespace = $wikiNamespace;
	}

	/**
	 * Try to map an ExpElement to a representative DataItem which may return null
	 * if the attempt fails.
	 *
	 * @since 2.4
	 *
	 * @param ExpElement $expElement
	 *
	 * @return DataItem|null
	 */
	public function tryToFindDataItemForExpElement( ExpElement $expElement ) {

		$dataItem = null;

		if ( !$expElement instanceof ExpResource ) {
			return $dataItem;
		}

		$uri = $expElement->getUri();

		if ( strpos( $uri, $this->wikiNamespace ) !== false ) {
			$dataItem = $this->tryToMatchDataItemForWikiNamespaceUri( $uri );
		} else {
			 // Not in wikiNamespace therefore most likely an imported URI
			$dataItem = $this->tryToMatchDataItemForUnmatchedWikiNamespaceUri( $uri );
		}

		return $dataItem;
	}

	private function tryToMatchDataItemForWikiNamespaceUri( $uri ) {

		$dataItem = null;
		$localName = substr( $uri, strlen( $this->wikiNamespace ) );

		$dbKey = rawurldecode( Escaper::decodeUri( $localName ) );
		$parts = explode( '#', $dbKey, 2 );

		if ( count( $parts ) == 2 ) {
			$dbKey = $parts[0];
			$subobjectname = $parts[1];
		} else {
			$subobjectname = '';
		}

		$parts = explode( ':', $dbKey, 2 );

		// No extra NS
		if ( count( $parts ) == 1 ) {
			return new DIWikiPage( $dbKey, NS_MAIN, '', $subobjectname );
		}

		$namespaceId = $this->tryToMatchNamespaceName( $parts[0] );

		if ( $namespaceId != -1 && $namespaceId !== false ) {
			$dataItem = new DIWikiPage( $parts[1], $namespaceId, '', $subobjectname );
		} else {
			$title = Title::newFromDBkey( $dbKey );

			if ( $title !== null ) {
				$dataItem = new DIWikiPage( $title->getDBkey(), $title->getNamespace(), $title->getInterwiki(), $subobjectname );
			}
		}

		return $dataItem;
	}

	private function tryToMatchNamespaceName( $name ) {
		// try the by far most common cases directly before using Title
		$namespaceName = str_replace( '_', ' ', $name );
		$namespaceId = -1;

		if ( ( $namespaceId = Localizer::getInstance()->getNamespaceIndexByName( $name ) ) !== false ) {
			return $namespaceId;
		}

		foreach ( array( SMW_NS_PROPERTY, NS_CATEGORY, NS_USER, NS_HELP ) as $nsId ) {
			if ( $namespaceName == Localizer::getInstance()->getNamespaceTextById( $nsId ) ) {
				$namespaceId = $nsId;
				break;
			}
		}

		return $namespaceId;
	}

	private function tryToMatchDataItemForUnmatchedWikiNamespaceUri( $uri ) {

		$dataItem = null;

		$respositoryResult = $this->store->getConnection( 'sparql' )->select(
			'?v1 ?v2',
			"<$uri> rdfs:label ?v1 . <$uri> swivt:wikiNamespace ?v2",
			array( 'LIMIT' => 1 )
		);

		$expElements = $respositoryResult->current();

		if ( $expElements !== false ) {

			// ?v1
			if ( isset( $expElements[0] ) ) {
				$dbKey = $expElements[0]->getLexicalForm();
			} else {
				$dbKey = 'UNKNOWN';
			}

			// ?v2
			if ( isset( $expElements[1] ) ) {
				$namespace = strval( $expElements[1]->getLexicalForm() );
			} else {
				$namespace = NS_MAIN;
			}

			$dataItem = new DIWikiPage( $dbKey, $namespace );
		}

		return $dataItem;
	}

}