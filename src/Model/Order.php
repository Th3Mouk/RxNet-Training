<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Model;

use Carbon\Carbon;

class Order
{
    public static function generate()
    {
        $faker = \Faker\Factory::create('fr_FR');

        return [
            'name' => $faker->firstname . ' ' . $faker->lastname,
            'adress' => $faker->address,
            'phone' => $faker->e164PhoneNumber,
            'time' => new Carbon(),
            'delivery' => 'nazgul',
            'pizzas' => [
                ['name' => 'isengard special', 'size' => 'medium'],
                ['name' => 'spicy sauron', 'size' => 'large']
            ],
            'totalAmount' => 12.99
        ];
    }
}
