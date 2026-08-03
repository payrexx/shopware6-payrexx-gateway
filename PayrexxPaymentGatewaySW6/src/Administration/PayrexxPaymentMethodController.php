<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Administration;

use Payrexx\Payrexx;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class PayrexxPaymentMethodController
{
    /**
     * @Route("/api/_action/payrexx_payment/validate-api-credentials", name="api.action.payrexx_payment.validate.api.credentials", methods={"POST"})
     * @throws \Payrexx\PayrexxException
     */
    #[Route(path: '/api/_action/payrexx_payment/validate-api-credentials', name: 'api.action.payrexx_payment.validate.api.credentials', methods: ['POST'])]
    public function validateApiCredentials(Request $request, Context $context): JsonResponse
    {
        if (!class_exists(Payrexx::class)) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        }

        $config = $request->get('credentials', []);

        $platform = !empty($config['platform']) ? $config['platform'] : '';
        $payrexx = new Payrexx($config['instanceName'], $config['apiKey'], '', $platform);

        $signatureCheck = new \Payrexx\Models\Request\SignatureCheck();

        $error = '';
        try {
            $payrexx->getOne($signatureCheck);
        } catch(\Payrexx\PayrexxException $e) {
            $error = $e;
        }
        return new JsonResponse(['credentialsValid' => !$error, 'error' => $error]);

    }

}
