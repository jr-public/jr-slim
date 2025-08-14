<?php
namespace App\Entity;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['username'])]
#[ORM\Index(columns: ['email'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['client_id'])]
#[ORM\Index(columns: ['created'])]
#[ORM\Index(columns: ['username', 'client_id'])] // Compound for login
#[ORM\Index(columns: ['email', 'client_id'])]    // Compound for email lookup
#[UniqueConstraint(fields:['username', 'client'])]
#[UniqueConstraint(fields:['email', 'client'])]
class User {
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable:false)]
    private Client $client;

    #[ORM\Column(type: 'string')]
    private string $username;

     #[ORM\Column(type: 'string')]
    private string $email;

     #[ORM\Column(type: 'string')]
    private string $role = 'user';

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'string')]
    private string $status = 'pending';

    #[ORM\Column(type: 'boolean')]
    private bool $reset_password = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created;
    
    public function __construct() {
        $this->created = new \DateTimeImmutable();
    }
    public function __toString() {
        return json_encode($this->toArray());
    }
    public function toArray(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'password' => $this->password,
            'created' => $this->created,
            'status' => $this->status,
            'reset_password' => $this->reset_password,
            'client' => $this->client->toArray()
        ];
    }
    public function get( string $prop ) {
        return $this->$prop ?? null;
    }
    public function has( string $prop ) {
        return property_exists($this, $prop);
    }
    
    public function setClient( Client $client ): self {
        $this->client = $client;
        return $this;
    }
    public function setUsername( string $username ): self {
        $this->username = $username;
        return $this;
    }
    public function setEmail(string $email): self {
        $this->email = $email;
        return $this;
    }
    public function setPassword(string $password): self {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        return $this;
    }
    public function activate(): self {
        if ( $this->status == 'pending' ) {
            $this->status = 'active';
        }
        return $this;
    }
    public function block(): self {
        if ( $this->status != 'blocked' ) {
            $this->status = 'blocked';
        }
        return $this;
    }
    public function unblock(): self {
        if ( $this->status == 'blocked' ) {
            $this->status = 'active';
        }
        return $this;
    }
    public function resetPassword(): self {
        $this->reset_password = true;
        return $this;
    }
    public function resetedPassword( string $new_password ): self {
        $this->reset_password = false;
        $this->setPassword($new_password);
        return $this;
    }
    
}
