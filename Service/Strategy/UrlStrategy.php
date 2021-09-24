<?php

namespace Fondy\Fondy\Service\Strategy;

final class UrlStrategy extends AbstractStrategy
{
    const NAME = 'url';

    /**
     * @param object $response
     * @return string|bool
     */
    public function retrieveCredentialsFromResponse($response)
    {
        if (isset($response->checkout_url)) {
            return $response->checkout_url;
        }

        return false;
    }
}
