<?php return array(
    'root' => array(
        'name' => 'payrexx/payment',
        'pretty_version' => '1.0.45',
        'version' => '1.0.45.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '1.0.45',
            'version' => '1.0.45.0',
            'reference' => null,
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v1.8.10',
            'version' => '1.8.10.0',
            'reference' => 'ab932dca32c607cbaa6f8da9b9e1de2efe28021f',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
