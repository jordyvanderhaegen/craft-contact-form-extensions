<?php

namespace hybridinteractive\contactformextensions\models;

use GuzzleHttp\Client;

class RecaptchaV3
{

    // Standard reCAPTCHA
    // const API_URI = 'https://www.google.com/recaptcha/api.js';
    // const VERIFY_URI = 'https://www.google.com/recaptcha/api/siteverify';

    // Need to add check for option of using recaptcha.net instead.
    // const API_URI = 'https://www.recaptcha.net/recaptcha/api.js';
    // const VERIFY_URI = 'https://www.recaptcha.net/recaptcha/api/siteverify';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    private $siteKey;
    private $secretKey;
    private $recaptchaUrl;
    private $recaptchaVerificationUrl;
    private $threshold;
    private $hideBadge;

    public function __construct(string $siteKey, string $secretKey, string $recaptchaUrl, string $recaptchaVerificationUrl, float $threshold, int $timeout = 5, bool $hideBadge = false)
    {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->recaptchaUrl = $recaptchaUrl;
        $this->recaptchaVerificationUrl = $recaptchaVerificationUrl;

        $this->client = new Client([
            'timeout' => $timeout,
        ]);
        $this->threshold = $threshold;
        $this->hideBadge = $hideBadge;
    }

    public function render($action = 'homepage')
    {
        $siteKey = $this->siteKey;
        // $api_uri = static::API_URI;
        $api_uri = $this->recaptchaUrl;

        $html = <<<HTML
                <script src="${api_uri}?onload=onloadRecaptcha&render=${siteKey}" async defer></script>
                <script>
                    var onloadRecaptcha = function() {
                        grecaptcha.ready(function() {
                            var input=document.getElementById('g-recaptcha-response');
                            var form=input.parentElement;
                            while(form && form.tagName.toLowerCase()!='form') {
                                form = form.parentElement;
                            }

                            if (form) {
                                form.addEventListener('submit',function(e) {
                                    e.preventDefault();  
                                    e.stopImmediatePropagation();

                                    if (input.value == '') {
                                        grecaptcha.execute('${siteKey}', {action: '${action}'}).then(function(token) {
                                            input.value = token;
                                            form.submit();
                                        });
                                    }

                                    return false;
                                },false);
                            }
                        });
                    };
                </script>

                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response" value="">
HTML;

        if ($this->hideBadge) {
            $html .= '<style>.grecaptcha-badge{display:none;!important}</style>'.PHP_EOL;
        }

        return $html;
    }

    public function verifyResponse($response, $clientIp)
    {
        if (empty($response)) {
            return false;
        }

        $response = $this->sendVerifyRequest([
            'secret'   => $this->secretKey,
            'remoteip' => $clientIp,
            'response' => $response,
        ]);

        if (!isset($response['success']) || $response['success'] !== true) {
            return false;
        }

        if (isset($response['score']) && $response['score'] >= $this->threshold) {
            return true;
        }

        return false;
    }

    protected function sendVerifyRequest(array $query = [])
    {
        $response = $this->client->post($this->recaptchaVerificationUrl, [
            'form_params' => $query,
        ]);

        return json_decode($response->getBody(), true);
    }
}
