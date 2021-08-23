<?php return array(
    'root' => array(
        'pretty_version' => '1.0.12',
        'version' => '1.0.12.0',
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => NULL,
        'name' => 'payrexx/payment',
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '1.0.12',
            'version' => '1.0.12.0',
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => NULL,
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => '1.7.3',
            'version' => '1.7.3.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'reference' => '1a779326b1bbd317dc942ad2db60ec564bd98b68',
            'dev_requirement' => false,
        ),
    ),
);
