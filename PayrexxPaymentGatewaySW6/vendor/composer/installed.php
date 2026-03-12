<?php return array(
    'root' => array(
        'name' => 'payrexx/payment',
        'pretty_version' => '2.1.12',
        'version' => '2.1.12.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '2.1.12',
            'version' => '2.1.12.0',
            'reference' => null,
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v2.0.12',
            'version' => '2.0.12.0',
            'reference' => '3c65ea106c2b2ca444fd3c823fbc78be7cfeb75a',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
