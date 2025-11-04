<?php

namespace App\Services\Brevo\Facades;

use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoEmail;
use App\Services\Brevo\BrevoResponse;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BrevoResponse sendTemplateEmail(BrevoEmail $email)
 * @method static BrevoResponse sendEmail(BrevoEmail $email)
 * @method static array getAccount()
 * @method static array getTemplates()
 * @method static array getTemplate(int $templateId)
 * @method static bool testConnection()
 *
 * @see BrevoClient
 */
class Brevo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'brevo';
    }
}
