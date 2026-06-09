<?php
declare(strict_types=1);

namespace Magento\Framework\Serialize\Serializer;

class Json
{
    public function serialize(mixed $data): string { return ''; }
    public function unserialize(string $string, mixed ...$args): mixed { return []; }
}
