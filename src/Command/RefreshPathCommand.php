<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCacheBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command to trigger cache refresh by path from the command line.
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
#[AsCommand(name: 'fos:httpcache:refresh:path')]
class RefreshPathCommand extends BaseInvalidateCommand
{
    use PathSanityCheck;

    protected function configure(): void
    {
        $this
            ->setName('fos:httpcache:refresh:path')
            ->setDescription('Refresh paths on all configured caching proxies')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'URL paths you want to refresh, you can specify any number of paths'
            )
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command refreshes a list of paths on the configured caching proxies.

Example:

    <info>php %command.full_name% /some/path /other/path</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getArgument('paths');

        foreach ($paths as $path) {
            if ($this->looksLikeRegularExpression($path)) {
                $output->writeln(sprintf('Path %s looks like a regular expression. Refresh requests operate with actual requests and thus use exact paths. Use regex invalidation for regular expressions.', $path));
            }

            $this->getCacheManager()->refreshPath($path);
        }

        return 0;
    }
}
