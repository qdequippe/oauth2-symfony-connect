<?php

namespace Qdequippe\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class SymfonyConnectResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var \DOMElement
     */
    private $data;

    /**
     * @var \DOMXpath
     */
    private $xpath;

    public function __construct($response)
    {
        $dom = new \DOMDocument();
        if (!$dom->loadXML($response['xml'])) {
            throw new \InvalidArgumentException('Could not transform this xml to a \DOMDocument instance.');
        }

        $this->xpath = new \DOMXpath($dom);

        $nodes = $this->xpath->evaluate('/api/root');
        $user = $this->xpath->query('./foaf:Person', $nodes->item(0));

        if (1 !== $user->length) {
            throw new \InvalidArgumentException('Could not retrieve valid user info.');
        }

        $this->data = $user->item(0);
    }

    /**
     * @return mixed
     **/
    public function getId()
    {
        return $this->data->attributes->getNamedItem('id')->value;
    }

    public function getUsername()
    {
        $accounts = $this->xpath->query('./foaf:account/foaf:OnlineAccount', $this->data);
        for ($i = 0; $i < $accounts->length; ++$i) {
            $account = $accounts->item($i);
            if ('SymfonyConnect' === $this->getNodeValue('./foaf:name', $account)) {
                return $this->getNodeValue('foaf:accountName', $account);
            }
        }

        return null;
    }

    public function getName()
    {
        return $this->getUsername() ?: $this->getNodeValue('./foaf:name', $this->data);
    }

    public function getEmail()
    {
        return $this->getNodeValue('./foaf:mbox', $this->data);
    }

    public function getProfilePicture()
    {
        return $this->getLinkNodeHref('./atom:link[@rel="foaf:depiction"]', $this->data);
    }

    public function getData()
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'username' => $this->getUsername(),
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'profilePicture' => $this->getProfilePicture(),
            'realname' => $this->getNodeValue('./foaf:name', $this->data),
            'biography' => $this->getNodeValue('./bio:olb', $this->data),
            'birthday' => $this->getNodeValue('./foaf:birthday', $this->data),
            'city' => $this->getNodeValue('./vcard:locality', $this->data),
            'country' => $this->getNodeValue('./vcard:country-name', $this->data),
            'company' => $this->getNodeValue('./cv:hasWorkHistory/cv:employedIn', $this->data),
            'jobPosition' => $this->getNodeValue('./cv:hasWorkHistory/cv:jobTitle', $this->data),
            'blogUrl' => $this->getNodeValue('./foaf:weblog', $this->data),
            'url' => $this->getNodeValue('./foaf:homepage', $this->data),
            'feedUrl' => $this->getNodeValue('./atom:link[@title="blog/feed"]', $this->data),
        ];
    }

    protected function getNodeValue($query, \DOMNode $element = null, $index = 0)
    {
        $nodeList = $this->xpath->query($query, $element);
        if ($nodeList->length > 0 && $index <= $nodeList->length) {
            return $this->sanitizeValue($nodeList->item($index)->nodeValue);
        }
    }

    protected function getLinkNodeHref($query, \DOMNode $element = null, $position = 0)
    {
        $nodeList = $this->xpath->query($query, $element);
        if ($nodeList && $nodeList->length > 0 && $nodeList->item($position)) {
            return $this->sanitizeValue($nodeList->item($position)->attributes->getNamedItem('href')->value);
        }
    }

    protected function sanitizeValue($value)
    {
        if ('true' === $value) {
            return true;
        }

        if ('false' === $value) {
            return false;
        }

        if (empty($value)) {
            return null;
        }

        return $value;
    }
}
