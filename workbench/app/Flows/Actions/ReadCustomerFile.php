<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Reads the uploaded customer CSV.
 *
 * The workbench has no uploads, so the rows are generated from the file name:
 * the same file always yields the same customers.
 */
final class ReadCustomerFile
{
    private const array COMPANIES = [
        'Acme Corporation', 'Globex', 'Initech', 'Umbrella Supply', 'Stark Fasteners',
        'Wayne Hardware', 'Hooli', 'Vandelay Industries', 'Soylent Foods', 'Tyrell Systems',
        'Cyberdyne Doors', 'Wonka Confectionery', 'Gringotts Holdings', 'Oceanic Freight',
    ];

    /**
     * @return array<int, array{id: string, name: string, email: string}>
     */
    public function execute(string $file): array
    {
        $count = 8 + crc32($file) % 7;
        $offset = crc32(strrev($file)) % count(self::COMPANIES);
        $customers = [];

        for ($i = 0; $i < $count; $i++) {
            $name = self::COMPANIES[($offset + $i) % count(self::COMPANIES)];
            $domain = strtolower((string) preg_replace('/[^a-z]/i', '', $name)).'.test';

            $customers[] = [
                'id' => sprintf('CUST-%04d', 1000 + $offset * 10 + $i),
                'name' => $name,
                'email' => 'accounts@'.$domain,
            ];
        }

        // Exports from the old CRM carry a typo it never validated.
        if (str_contains($file, 'legacy')) {
            $customers[4]['email'] = str_replace('@', '@@', $customers[4]['email']);
        }

        return $customers;
    }
}
