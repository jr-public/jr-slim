<?php
namespace App\Entity;
use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
class Client {
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int|null $id = null;
    #[ORM\Column(type: 'string')]
    private string $name;
    #[ORM\Column(type: 'string')]
    private string $domain;
    
    public function __toString() {
        return json_encode($this->toArray());
    }
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
    public function get( string $prop ) {
		return $this->$prop ?? null;
	}
	public function init( array $data ): self {
		$this->name     = $data['name'];
		$this->domain   = $data['domain'];
		return $this;
	}
}