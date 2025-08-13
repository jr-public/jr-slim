<?php
namespace App\Entity;

// use App\Repository\TokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'tokens')]
class Token
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    // Hash of the raw token string — never store the raw token
    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $token_hash;

    // Purpose: "password_reset", "email_verification", etc.
    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expires_at;

    #[ORM\Column(type: 'boolean')]
    private bool $used = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created;

    public function __construct(string $id, User $user, string $type, string $token_hash, \DateTimeImmutable $expires_at)
    {
        $this->id = $id;
        $this->user = $user;
        $this->type = $type;
        $this->token_hash = $token_hash;
        $this->expires_at = $expires_at;
        $this->created = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user->get('id'),
            'type'       => $this->type,
            'expires_at' => $this->expires_at,
            'used'       => $this->used,
            'created'    => $this->created,
        ];
    }

    public function get(string $prop)
    {
        return $this->$prop ?? null;
    }

    public function has(string $prop): bool
    {
        return property_exists($this, $prop);
    }

    public function markUsed(): self
    {
        $this->used = true;
        return $this;
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expires_at;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }
}
