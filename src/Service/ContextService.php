<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Client;
use App\Exception\NotFoundException;
// PENSE QUE LO IBA A NECESITAR EN USER SERVICE PERO PARECE QUE NO
// NO SE ESTA USANDO EN NINGUN OTRO LUGAR
class ContextService
{
    private ?Client $client = null;
    private ?User $activeUser = null;
    private ?User $targetUser = null;
    private ?array $forcedFilters = null;
    private ?string $device = null;

    /**
     * Check if user is set in context
     * @return bool
     */
    public function hasActiveUser(): bool
    {
        return !empty($this->activeUser);
    }
    public function hasTargetUser(): bool
    {
        return !empty($this->targetUser);
    }

    /**
     * Check if client is set in context
     * @return bool
     */
    public function hasClient(): bool
    {
        return !empty($this->client);
    }


    /**
     * Set the current user
     * @param User $user
     * @return self
     */
    public function setActiveUser(User $user): self
    {
        $this->activeUser = $user;
        return $this;
    }
    public function setTargetUser(User $user): self
    {
        $this->targetUser = $user;
        return $this;
    }

    /**
     * Set the current client
     * @param Client $client
     * @return self
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Get the current user
     * @return User
     * @throws NotFoundException When user is not set in context
     */
    public function getActiveUser(): User
    {
        if (!$this->hasActiveUser()) {
            throw new NotFoundException('BAD_USER', "Request context active user not found");
        }
        return $this->activeUser;
    }
    public function getTargetUser(): User
    {
        if (!$this->hasTargetUser()) {
            throw new NotFoundException('BAD_USER', "Request context target user not found");
        }
        return $this->targetUser;
    }

    public function getClient(): Client
    {
        if (!$this->hasClient()) {
            throw new NotFoundException('BAD_CLIENT', "Request context client not found");
        }
        return $this->client;
    }
    public function setForcedFilters(array $forcedFilters): self
    {
        $this->forcedFilters = $forcedFilters;
        return $this;
    }
    public function getForcedFilters(): array
    {
        if (!$this->hasForcedFilters()) {
            throw new NotFoundException('BAD_FILTERS', "Request context forced filters not found");
        }
        return $this->forcedFilters;
    }
    public function hasForcedFilters(): bool
    {
        return !empty($this->forcedFilters);
    }
    // /**
    //  * Check if device is set in context
    //  * @return bool
    //  */
    // public function hasDevice(): bool
    // {
    //     return !empty($this->device);
    // }
    // /**
    //  * Set the current device identifier
    //  * @param string $device
    //  * @return self
    //  */
    // public function setDevice(string $device): self
    // {
    //     $this->device = $device;
    //     return $this;
    // }
    // public function getDevice(): string
    // {
    //     if (!$this->hasDevice()) {
    //         throw new NotFoundException('BAD_DEVICE', "Request context device not found");
    //     }
    //     return $this->device;
    // }
}
