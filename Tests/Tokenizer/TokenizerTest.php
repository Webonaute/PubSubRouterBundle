<?php

namespace Gos\Bundle\PubSubRouterBundle\Tests\Tokenizer;

use Gos\Bundle\PubSubRouterBundle\Router\Route;
use Gos\Bundle\PubSubRouterBundle\Tokenizer\Token;
use Gos\Bundle\PubSubRouterBundle\Tokenizer\Tokenizer;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $expression
     * @param bool   $isParameter
     * @param array  $requirements
     *
     * @return object
     */
    protected function createToken($expression, $isParameter = false, $requirements = [])
    {
        $token = $this->prophesize(Token::CLASS);
        $token->isParameter()->willReturn($isParameter);
        $token->getExpression()->willReturn($expression);
        $token->getRequirements()->willReturn($requirements);

        return $token->reveal();
    }

    /**
     * @param string $pattern
     * @param string $name
     * @param array  $requirements
     *
     * @return \Prophecy\Prophecy\ObjectProphecy
     */
    protected function createRoute($pattern, $name, $requirements = [])
    {
        $route = $this->prophesize(Route::CLASS);
        $route->getPattern()->willReturn($pattern);
        $route->getRequirements()->willReturn($requirements);
        $route->__toString()->willReturn($name);

        return $route;
    }

    /**
     * @dataProvider provideData
     */
    public function testTokenize($channelOrString, $separator, Array $expectedTokens, $inverse = false)
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize($channelOrString, $separator);
        $method = $inverse ? 'assertNotEquals' : 'assertEquals';

        foreach ($expectedTokens as $i => $token) {
            $this->{$method}($tokens[$i]->getRequirements(), $expectedTokens[$i]->getRequirements());
            $this->{$method}($tokens[$i]->isParameter(), $expectedTokens[$i]->isParameter());
            $this->{$method}($tokens[$i]->getExpression(), $expectedTokens[$i]->getExpression());
        }
    }

    public function provideData()
    {
        $results = [];

        $notifUserRoute = $this->createRoute('notification/user/{uid}', 'user_notification', ['uid' => ['pattern' => "\d+", 'wildcard' => true]]);
        $notifAppliUserRoute = $this->createRoute('notification/application/user/{role}/{uid}', 'user_application_notification', [
            'uid' => ['pattern' => "\d+", 'wildcard' => true],
            'role' => ['pattern' => 'admin|client'],
        ]);

        $tests = [
            [
                'route' => 'notification/user/18',
                'separator' => '/',
                'tokens' => [
                    $this->createToken('notification'),
                    $this->createToken('user'),
                    $this->createToken('18'),
                ],
            ],
            [
                'route' => $notifUserRoute->reveal(),
                'separator' => '/',
                'tokens' => [
                    $this->createToken('notification'),
                    $this->createToken('user'),
                    $this->createToken('uid', true, ['pattern' => "\d+", 'wildcard' => true]),
                ],
            ],
            [
                'route' => $notifAppliUserRoute->reveal(),
                'separator' => '/',
                'tokens' => [
                    $this->createToken('notification'),
                    $this->createToken('application'),
                    $this->createToken('user'),
                    $this->createToken('role', true, ['pattern' => 'admin|client']),
                    $this->createToken('uid', true, ['pattern' => "\d+", 'wildcard' => true]),
                ],
            ],
            [
                'route' => 'notification:user:*',
                'separator' => ':',
                'tokens' => [
                    $this->createToken('notification'),
                    $this->createToken('user'),
                    $this->createToken('*'),
                ],
            ],
        ];

        foreach ($tests as $type => $conf) {
            $results[] = [$conf['route'], $conf['separator'], $conf['tokens']];
        }

        return $results;
    }
}
