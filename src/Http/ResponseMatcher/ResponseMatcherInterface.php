<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCacheBundle\Http\ResponseMatcher;

use Symfony\Component\HttpFoundation\Response;

interface ResponseMatcherInterface
{
    /**
     * Determines whether the response matches the rule.
     */
    public function matches(Response $response): bool;
}
