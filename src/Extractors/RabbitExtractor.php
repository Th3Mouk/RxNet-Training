<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Extractors;

use Rxnet\RabbitMq\RabbitMessage;
use Underscore\Types\Arrays;

class RabbitExtractor
{
    /**
     * Extract a value in the labels then in the datas
     *
     * @param RabbitMessage $message
     * @param string $path
     * @return null|string
     */
    public static function extract(RabbitMessage $message, string $path)
    {
        $labels = $message->getLabels();
        $datas = $message->getData();

        $label = Arrays::get($labels, $path);

        // If no label then datas or null
        return null !== $label ? $label : Arrays::get($datas, $path);
    }
}
