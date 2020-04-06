<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

use Shopware\Core\Framework\Context;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @RouteScope(scopes={"api"})
 */
class SettingsController extends AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     **/
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger
    ) {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/v{version}/_action/payrexx_payment/validate-api-credentials", name="api.action.payrexx_payment.validate.api.credentials", methods={"POST"})
     * @throws \Payrexx\PayrexxException
     */
    public function validateApiCredentials(Request $request, Context $context): JsonResponse
    {
        require_once dirname(dirname(__DIR__)). '/vendor/autoload.php';
        $config = $request->get('credentials', []);

        $payrexx = new \Payrexx\Payrexx($config['instanceName'], $config['apiKey']);

        $signatureCheck = new \Payrexx\Models\Request\SignatureCheck();

        $error = '';
        try {
            $response = $payrexx->getOne($signatureCheck);
        } catch(\Payrexx\PayrexxException $e) {
            $error = $e;
        }
        return new JsonResponse(['credentialsValid' => !$error, 'error' => $error]);

    }

}
