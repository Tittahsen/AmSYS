<?php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Entity\TransactionEntity;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table(name="lineitems")
 */
class LineItemEntity
{
    public function __construct()
    {
        $this->isValid = true;
    }
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $typeId;

    public function setTypeId($typeId)
    {
        $this->typeId = $typeId;

        return $this;
    }

    public function getTypeId()
    {
        return $this->typeId;
    }

    /**
     * @ORM\Column(type="bigint")
     */
    protected $quantity;

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @ORM\Column(type="decimal", precision=19, scale=2)
     */
    protected $marketPrice;

    public function setMarketPrice($marketPrice)
    {
        $this->marketPrice = $marketPrice;

        return $this;
    }

    public function getMarketPrice()
    {
        return $this->marketPrice;
    }

    /**
     * @ORM\Column(type="decimal", precision=19, scale=2)
     */
    protected $grossPrice;

    public function setGrossPrice($grossPrice)
    {
        $this->grossPrice = $grossPrice;

        return $this;
    }

    public function getGrossPrice()
    {
        return $this->grossPrice;
    }

    /**
     * @ORM\Column(type="decimal", precision=19, scale=2)
     */
    protected $netPrice;

    public function setNetPrice($netPrice)
    {
        $this->netPrice = $netPrice;

        return $this;
    }

    public function getNetPrice()
    {
        return $this->netPrice;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @ORM\ManyToOne(targetEntity="TransactionEntity", inversedBy="lineitems")
     * @ORM\JoinColumn(name="transaction_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $transaction;

    /**
     * Set transaction
     *
     * @param \AppBundle\Entity\TransactionEntity $transaction
     *
     * @return LineItemEntity
     */
    public function setTransaction(TransactionEntity $transaction = null)
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * Get transaction
     *
     * @return \AppBundle\Entity\LineItemEntity
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    protected $isValid;

    public function setIsValid($isValid)
    {
        $this->isValid = $isValid;

        return $this;
    }

    public function getIsValid()
    {
        return $this->isValid;
    }
}
