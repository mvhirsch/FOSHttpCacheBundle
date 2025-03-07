<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCacheBundle\Tests\Functional\Fixtures\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class FlashMessageController extends AbstractController
{
    public function flashAction(): Response
    {
        $this->addFlash(
            'notice',
            'Flash Message!'
        );

        return new Response('flash');
    }

    public function flashRedirectAction(): RedirectResponse
    {
        $this->addFlash(
            'notice',
            'Flash Message!'
        );

        return new RedirectResponse('/flash');
    }
}
