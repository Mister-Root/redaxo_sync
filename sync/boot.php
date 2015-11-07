<?php
/**
 * This file is part of the sync package.
 *
 * @author (c) Pascal Ruscher <pascal@dev-train.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
rex_extension::register('PACKAGES_INCLUDED', function () 
{
    if ((rex_addon::get('sync')->getConfig('backend_sync') && rex::isBackend())
	|| (rex_addon::get('sync')->getConfig('frontend_sync') && !rex::isBackend())) {
	if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
	    Sync::load();
	}
    }
});