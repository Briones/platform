<?php

namespace Oro\Bundle\ImportExportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityBundle\EntityProperty\CreatedAtAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\MagentoBundle\Entity\CreatedAtAwareInterface;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Entity\Ownership\OrganizationAwareTrait;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * Entity holds information about import/export operations
 *
 * @ORM\Entity
 * @ORM\Table(name="oro_import_export_result")
 * @Config(
 *     defaultValues={
 *          "ownership"={
 *              "owner_type"="USER",
 *              "owner_field_name"="owner",
 *              "owner_column_name"="owner_id",
 *              "organization_field_name"="organization",
 *              "organization_column_name"="organization_id"
 *          },
 *          "security"={
 *              "type"="ACL"
 *          }
 *     }
 * )
 *
 * @ORM\HasLifecycleCallbacks()
 */
class ImportExportResult implements CreatedAtAwareInterface
{
    use CreatedAtAwareTrait;
    use OrganizationAwareTrait;

    /**
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $owner;

    /**
     * @var Organization|null
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OrganizationBundle\Entity\Organization")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $organization;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, name="filename", unique=true, nullable=true)
     */
    protected $filename;

    /**
     * @var integer
     *
     * @ORM\Column(name="job_id", type="integer", unique=true, nullable=false)
     */
    protected $jobId;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, name="job_code", nullable=true)
     */
    protected $jobCode;

    /**
     * @var boolean
     *
     * @ORM\Column(name="expired", type="boolean", options={"default"=false})
     */
    protected $expired = false;

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User|null
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /**
     * @param User $owner
     *
     * @return $this
     */
    public function setOwner(User $owner): ImportExportResult
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     *
     * @return $this
     */
    public function setFilename(string $filename = null): ImportExportResult
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return int
     */
    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    /**
     * @param int $jobId
     *
     * @return $this
     */
    public function setJobId(int $jobId): ImportExportResult
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getJobCode(): ?string
    {
        return $this->jobCode;
    }

    /**
     * @param string $jobCode
     *
     * @return $this
     */
    public function setJobCode(string $jobCode = null): ImportExportResult
    {
        $this->jobCode = $jobCode;

        return $this;
    }

    /**
     * @return bool
     */
    public function isExpired(): ?bool
    {
        return $this->expired;
    }

    /**
     * @param bool $expired
     *
     * @return $this
     */
    public function setExpired(bool $expired): ImportExportResult
    {
        $this->expired = $expired;

        return $this;
    }
}
