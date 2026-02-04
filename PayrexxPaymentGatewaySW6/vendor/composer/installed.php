<?php return array(
    'root' => array(
        'name' => 'payrexx/payment',
        'pretty_version' => '2.1.10',
        'version' => '2.1.10.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '2.1.10',
            'version' => '2.1.10.0',
            'reference' => null,
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v1.8.11',
            'version' => '1.8.11.0',
            'reference' => '30bd92fa3d56586a06705477fafdba7b7bf3a0bd',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
