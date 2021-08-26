<?php return array(
    'root' => array(
        'pretty_version' => '1.0.13',
        'version' => '1.0.13.0',
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => NULL,
        'name' => 'payrexx/payment',
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '1.0.13',
            'version' => '1.0.13.0',
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => NULL,
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v1.7.4',
            'version' => '1.7.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'reference' => '0cfdafe40e893b12df48d21cd83a5cf3c21b3055',
            'dev_requirement' => false,
        ),
    ),
);
