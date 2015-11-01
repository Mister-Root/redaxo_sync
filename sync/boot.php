<?php
/**
 * This file is part of the sync package.
 *
 * @author (c) Pascal Ruscher <pascal@dev-train.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
function reload()
{
    new Sync();
}
if (rex::isBackend() && rex::getUser()) {
    
    rex_extension::register('PAGE_HEADER', 'reload');
}