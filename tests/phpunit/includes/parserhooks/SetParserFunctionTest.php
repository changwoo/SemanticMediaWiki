<?php

namespace SMW\Tests;

use SMW\Tests\Util\UtilityFactory;

use SMW\SetParserFunction;
use SMW\ParameterFormatterFactory;
use SMW\Application;

use Title;
use ParserOutput;

/**
 * @covers \SMW\SetParserFunction
 *
 * @group SMW
 * @group SMWExtension
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class SetParserFunctionTest extends \PHPUnit_Framework_TestCase {

	private $application;
	private $semanticDataValidator;

	protected function setUp() {
		parent::setUp();

		$this->semanticDataValidator = UtilityFactory::getInstance()->newValidatorFactory()->newSemanticDataValidator();

		$this->application = Application::getInstance();
	}

	protected function tearDown() {
		$this->application->clear();

		parent::tearDown();
	}

	public function testCanConstruct() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$messageFormatter = $this->getMockBuilder( '\SMW\MessageFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			'\SMW\SetParserFunction',
			new SetParserFunction( $parserData, $messageFormatter )
		);
	}

	/**
	 * @dataProvider setParserProvider
	 */
	public function testParse( array $params, array $expected ) {

		$parserData = $this->application->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$messageFormatter = $this->getMockBuilder( '\SMW\MessageFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$messageFormatter->expects( $this->any() )
			->method( 'addFromArray' )
			->will( $this->returnSelf() );

		$messageFormatter->expects( $this->once() )
			->method( 'getHtml' )
			->will( $this->returnValue( 'Foo' ) );

		$instance = new SetParserFunction(
			$parserData,
			$messageFormatter
		);

		$this->assertInternalType(
			'string',
			$instance->parse( ParameterFormatterFactory::newFromArray( $params ) )
		);
	}

	/**
	 * @dataProvider setParserProvider
	 */
	public function testInstantiatedPropertyValues( array $params, array $expected ) {

		$parserData = $this->application->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$messageFormatter = $this->getMockBuilder( '\SMW\MessageFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$messageFormatter->expects( $this->any() )
			->method( 'addFromArray' )
			->will( $this->returnSelf() );

		$instance = new SetParserFunction(
			$parserData,
			$messageFormatter
		);

		$instance->parse( ParameterFormatterFactory::newFromArray( $params ) );

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()
		);
	}

	public function setParserProvider() {

		// #0 Single data set
		// {{#set:
		// |Foo=bar
		// }}
		$provider[] = array(
			array( 'Foo=bar' ),
			array(
				'errors' => 0,
				'propertyCount'  => 1,
				'propertyLabels' => 'Foo',
				'propertyValues' => 'Bar'
			)
		);

		// #1 Empty data set
		// {{#set:
		// |Foo=
		// }}
		$provider[] = array(
			array( 'Foo=' ),
			array(
				'errors' => 0,
				'propertyCount'  => 0,
				'propertyLabels' => '',
				'propertyValues' => ''
			)
		);

		// #2 Multiple data set
		// {{#set:
		// |BarFoo=9001
		// |Foo=bar
		// }}
		$provider[] = array(
			array( 'Foo=bar', 'BarFoo=9001' ),
			array(
				'errors' => 0,
				'propertyCount'  => 2,
				'propertyLabels' => array( 'Foo', 'BarFoo' ),
				'propertyValues' => array( 'Bar', '9001' )
			)
		);

		// #3 Multiple data set with an error record
		// {{#set:
		// |_Foo=9001 --> will raise an error
		// |Foo=bar
		// }}
		$provider[] = array(
			array( 'Foo=bar', '_Foo=9001' ),
			array(
				'errors' => 1,
				'propertyCount'  => 1,
				'propertyLabels' => array( 'Foo' ),
				'propertyValues' => array( 'Bar' )
			)
		);

		return $provider;
	}

}
