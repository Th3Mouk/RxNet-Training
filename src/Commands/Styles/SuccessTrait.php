<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Commands\Styles;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;

trait SuccessTrait
{
    public function getSuccessStyle()
    {
        return new OutputFormatterStyle('black', 'green');
    }
}
