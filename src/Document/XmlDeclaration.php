<?php

declare(strict_types=1);

namespace Kalle\Xml\Document;

use Kalle\Xml\Exception\InvalidXmlDeclarationException;

use function strcasecmp;

final readonly class XmlDeclaration
{
    private string $version;

    private ?string $encoding;

    private ?bool $standalone;

    public function __construct(
        string $version = '1.0',
        ?string $encoding = 'UTF-8',
        ?bool $standalone = null,
    ) {
        if ($version !== '1.0') {
            throw new InvalidXmlDeclarationException(sprintf(
                'XML version must be "1.0"; got "%s".',
                $version,
            ));
        }

        if ($encoding === '') {
            throw new InvalidXmlDeclarationException(
                'Encoding must be UTF-8 or null.',
            );
        }

        if ($encoding !== null && strcasecmp($encoding, 'UTF-8') !== 0) {
            throw new InvalidXmlDeclarationException(sprintf(
                'Encoding must be UTF-8; got "%s".',
                $encoding,
            ));
        }

        $this->version = $version;
        $this->encoding = $encoding === null ? null : 'UTF-8';
        $this->standalone = $standalone;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function encoding(): ?string
    {
        return $this->encoding;
    }

    public function standalone(): ?bool
    {
        return $this->standalone;
    }

    public function withVersion(string $version): self
    {
        if ($version === $this->version) {
            return $this;
        }

        return new self($version, $this->encoding, $this->standalone);
    }

    public function withEncoding(?string $encoding): self
    {
        if ($encoding === $this->encoding) {
            return $this;
        }

        if ($encoding !== null && $this->encoding !== null && strcasecmp($encoding, $this->encoding) === 0) {
            return $this;
        }

        return new self($this->version, $encoding, $this->standalone);
    }

    public function withStandalone(?bool $standalone): self
    {
        if ($standalone === $this->standalone) {
            return $this;
        }

        return new self($this->version, $this->encoding, $standalone);
    }
}
