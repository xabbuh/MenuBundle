<?php

namespace Symfony\Cmf\Bundle\MenuBundle\Tests;
use Symfony\Cmf\Bundle\MenuBundle\Document\MenuNode;
use Symfony\Cmf\Bundle\MenuBundle\ContentAwareFactory;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class ContentAwareFactoryTest extends \PHPUnit_Framework_Testcase
{
    public function setUp()
    {
        $this->pwfc = $this->getMock(
            'Symfony\Cmf\Bundle\CoreBundle\PublishWorkflow\PublishWorkflowCheckerInterface'
        );
        $this->urlGenerator = $this->getMock(
            'Symfony\Component\Routing\Generator\UrlGeneratorInterface'
        );
        $this->contentUrlGenerator = $this->getMock(
            'Symfony\Component\Routing\Generator\UrlGeneratorInterface'
        );
        $this->logger = $this->getMock(
            'Psr\Log\LoggerInterface'
        );

        $this->factory = new ContentAwareFactory(
            $this->urlGenerator,
            $this->contentUrlGenerator,
            $this->pwfc,
            $this->logger,
            false // refactore this empty items option
        );

        $this->node1 = $this->getMock('Knp\Menu\NodeInterface');
        $this->node2 = $this->getMock('Knp\Menu\NodeInterface');
        $this->node3 = $this->getMock('Knp\Menu\NodeInterface');

        $this->content = new \stdClass;
    }

    public function provideCreateFromNode()
    {
        return array(
            array(array(
            )),
            array(array(
                'node2_is_published' => false,
            )),
        );
    }

    /**
     * @dataProvider provideCreateFromNode
     */
    public function testCreateFromNode($options)
    {
        $options = array_merge(array(
            'node2_is_published' => true
        ), $options);

        $this->contentUrlGenerator->expects($this->any())
            ->method('generate')
            ->will($this->returnValue('foobar'));

        $this->node1->expects($this->once())
            ->method('getOptions')->will($this->returnValue(array()));
        $this->node3->expects($this->once())
            ->method('getOptions')->will($this->returnValue(array()));

        $this->node1->expects($this->once())
            ->method('getChildren')
            ->will($this->returnValue(array(
                $this->node2,
                $this->node3,
            )));

        $this->node3->expects($this->once())
            ->method('getChildren')
            ->will($this->returnValue(array()));

        $mock = $this->pwfc->expects($this->at(0))
            ->method('checkIsPublished')
            ->with($this->node2);

        if ($options['node2_is_published']) {
            $mock->will($this->returnValue(true));
            $this->node2->expects($this->once())
                ->method('getOptions')->will($this->returnValue(array()));
            $this->node2->expects($this->once())
                ->method('getChildren')
                ->will($this->returnValue(array()));
        } else {
            $mock->will($this->returnValue(false));
        }

        $this->pwfc->expects($this->at(1))
            ->method('checkIsPublished')
            ->with($this->node3);

        $res = $this->factory->createFromNode($this->node1);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $res);
    }

    public function provideCreateItem()
    {
        return array(
            array(array(
                'allow_empty_items' => false,
                'has_content_route' => true,
                'content_found' => false,
            )),

            array(array(
                'allow_empty_items' => true,
                'has_content_route' => true,
                'content_found' => false,
            )),

            array(array(
                'allow_empty_items' => true,

                'has_content_route' => true,
                'content_found' => false,
            )),

            array(array(
                'has_content_route' => true,
                'content_found' => true,
            )),

            // invalid link type
            array('test', array(
                'linkType' => 'invalid',
            )),

            // linkType == '' translates as URI
            array('test', array(
                'uri' => 'foobar',
                'linkType' => '',
            )),

            // URI is used when link type is URI
            array('test', array(
                'uri' => 'foobar',
                'linkType' => 'uri',
            )),

            // route is used when type is route
            array('test', array(
                'uri' => 'foobar',
                'route' => 'testroute',
                'linkType' => 'route',
            )),

            // route is used when linkType ommitted and URI
            // not set.
            array('test', array(
                'route' => 'testroute',
            )),

            // content is used when linkType ommitted and URI
            // and route not set.
            array('test', array(
            ), array(
                'provideContent' => true,
            )),

            // content is used when linkType ommitted and URI
            // and route not set.
            array('test', array(
                'uri' => 'foobar',
                'route' => 'barfoo',
                'linkType' => 'content',
            ), array(
                'provideContent' => true,
            )),
        );
    }

    /**
     * @dataProvider provideCreateItem
     */
    public function testCreateItem($name, $options, $testOptions = array())
    {
        $options = array_merge(array(
            'content' => null,
            'routeParameters' => array(),
            'routeAbsolute' => false,
            'uri' => null,
            'route' => null,
            'linkType' => null,
        ), $options);

        $testOptions = array_merge(array(
            'allowEmptyItems' => false,
            'provideContent' => false,
        ), $testOptions);

        if (true === $testOptions['allowEmptyItems']) {
            $this->factory->setAllowEmptyItems(true);
        }

        if (true === $testOptions['provideContent']) {
            $options['content'] = $this->content;
        }

        $this->prepareCreateItemTests($name, $options);

        $item = $this->factory->createItem($name, $options);

        if (in_array($options['linkType'], array('uri', ''))) {
            $this->assertEquals($options['uri'], $item->getUri());
        }

        $this->assertEquals($name, $item->getName());
    }

    protected function prepareCreateItemTests($name, $options)
    {
        if (
            is_null($options['uri']) &&
            is_null($options['route']) &&
            is_null($options['content']) &&
            !in_array($options['linkType'], array(
                'route', 
                'uri', 
                'content', 
                ''
            ))
        ) {
            $this->setExpectedException('\InvalidArgumentException');
        }

        if ($options['linkType'] == 'route') {
            $this->urlGenerator->expects($this->once())
                ->method('generate')
                ->with($options['route'], $options['routeParameters'], $options['routeAbsolute']);
        }

        if (
            null == $options['linkType'] && 
            empty($options['uri']) &&
            !empty($options['route'])
        ) {
            $this->urlGenerator->expects($this->once())
                ->method('generate')
                ->with($options['route'], $options['routeParameters'], $options['routeAbsolute']);
        }

        if ($options['linkType'] == 'content') {
            $this->contentUrlGenerator->expects($this->once())
                ->method('generate')
                ->with($options['content'], $options['routeParameters'], $options['routeAbsolute']);
        }

        if (
            null === $options['linkType'] && 
            empty($options['uri']) &&
            empty($options['route']) &&
            !empty($options['content'])
        ) {
            $this->contentUrlGenerator->expects($this->once())
                ->method('generate')
                ->with($options['content'], $options['routeParameters'], $options['routeAbsolute']);
        }
    }
}
