<?php

declare(strict_types=1);

namespace MiniShop3\Services\Cart;

/**
 * Resolve the MODX context key used for cart/order draft storage.
 *
 * Page/lexicon context and draft context can differ when ms3_cart_context is on:
 * lexicon stays on the page context, drafts stay in web.
 */
final class CartDraftContext
{
    public const DEFAULT_CONTEXT = 'web';

    /**
     * @param object $modx Runtime MODX (needs getOption)
     * @param string $pageCtx Context of the storefront page / API request
     */
    public static function resolve(object $modx, string $pageCtx): string
    {
        $pageCtx = $pageCtx !== '' ? $pageCtx : self::DEFAULT_CONTEXT;
        $unified = (bool) $modx->getOption('ms3_cart_context', null, '0', true);

        return $unified ? self::DEFAULT_CONTEXT : $pageCtx;
    }
}
