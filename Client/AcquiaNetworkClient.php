<?php

namespace Acquia\Network\Client;

use Acquia\Common\AcquiaServiceClient;
use Acquia\Network\Subscription;
use Guzzle\Common\Collection;

class AcquiaNetworkClient extends AcquiaServiceClient
{
    const NONCE_LENGTH = 55;

    /**
     * @var string
     */
    protected $networkId;

    /**
     * @var string
     */
    protected $networkKey;

    /**
     * @var \Acquia\Common\NoncerAbstract
     */
    protected $noncer;

    /**
     * {@inheritdoc}
     *
     * @return \Acquia\Network\Client\AcquiaNetworkClient
     */
    public static function factory($config = array())
    {
        $defaults = array(
            'base_url' => 'https://rpc.acquia.com',
        );

        $required = array(
            'base_url',
            'network_id',
            'network_key',
        );

        // Instantiate the Acquia Search plugin.
        $config = Collection::fromConfig($config, $defaults, $required);
        return new static(
            $config->get('base_url'),
            $config->get('network_id'),
            $config->get('network_key'),
            $config
        );
    }

    /**
     * @param string $networkUri
     * @param string $networkId
     * @param string $networkKey
     * @param mixed $config
     */
    public function __construct($networkUri, $networkId, $networkKey, $config = null)
    {
        $this->networkId = $networkId;
        $this->networkKey = $networkKey;
        $this->noncer = self::noncerFactory(self::NONCE_LENGTH);

        parent::__construct($networkUri, $config);
    }

    /**
     * @return string
     */
    public function getNetworkId()
    {
        return $this->networkId;
    }

    /**
     * @return string
     */
    public function getNetworkKey()
    {
        return $this->networkKey;
    }

    /**
     * @return \Acquia\Common\NoncerAbstract
     */
    public function getNoncer()
    {
        return $this->noncer;
    }

    /**
     * @return \Acquia\Network\Subscription
     */
    public function checkSubscription()
    {
        $signature = new Signature($this->networkId, $this->networkKey, $this->noncer);

        $serverAddress = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $https = isset($_SERVER['HTTPS']) ? 1 : 0;

        $body = '<?xml version="1.0"?>
          <methodCall>
            <methodName>acquia.agent.subscription</methodName>
            <params>
              <param>
                <value>
                  <struct>
                    <member><name>authenticator</name>
                      <value>
                        <struct>
                          <member><name>identifier</name><value><string>' . $this->networkId . '</string></value></member>
                          <member><name>time</name><value><int>' . $signature->getRequestTime() . '</int></value></member>
                          <member><name>hash</name><value><string>' . $signature->generate() . '</string></value></member>
                          <member><name>nonce</name><value><string>' . $signature->getNonce() . '</string></value></member>
                        </struct>
                      </value>
                    </member>
                    <member><name>ip</name><value><string>' . $serverAddress . '</string></value></member>
                    <member><name>host</name><value><string>' . $httpHost . '</string></value></member>
                    <member><name>ssl</name><value><boolean>' . $https . '</boolean></value></member>
                    <member>
                      <name>body</name>
                      <value>
                        <struct>
                          <member><name>search_version</name>
                            <value>
                              <struct>
                              </struct>
                            </value>
                          </member>
                        </struct>
                      </value>
                    </member>
                  </struct>
                </value>
              </param>
            </params>
          </methodCall>'
        ;

        $xml = $this->post('xmlrpc.php', array(), $body)->send()->xml();
        $xmlrpcResponse = new XmlrpcResponse($xml);
        return Subscription::loadFromResponse($this->networkId, $this->networkKey, $xmlrpcResponse);
    }
}
