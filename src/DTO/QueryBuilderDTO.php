<?php
namespace App\DTO;

use App\DTO\DataTransferObjectInterface;
use Symfony\Component\Validator\Constraints as Assert;

class QueryBuilderDTO implements DataTransferObjectInterface
{
    #[Assert\Type('integer')]
    #[Assert\Positive(message: 'ID must be a positive integer.')]
    public ?int $id = null;

    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['admin', 'user'],
        message: 'Invalid user role. Must be admin or user.'
    )]
    public ?string $role = null;

    #[Assert\Type('integer')]
    #[Assert\Positive(message: 'Client ID must be a positive integer.')]
    public ?int $client_id = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero(message: 'Limit must be a positive integer or zero.')]
    public ?int $limit = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero(message: 'Offset must be a positive integer or zero.')]
    public ?int $offset = null;

    #[Assert\Type('string')]
    public ?string $order_by = null;

    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['ASC', 'DESC'],
        message: 'Invalid order direction. Must be ASC or DESC.'
    )]
    public ?string $order = null;

    public function toArray(): array {
        return get_object_vars($this);
    }
}