<?php

namespace Sylius\ShopApiPlugin\Handler;

use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\ShopApiPlugin\Command\AddressOrder;

final class AddressOrderHandler
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AddressFactoryInterface
     */
    private $addressFactory;

    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param AddressFactoryInterface $addressFactory
     * @param FactoryInterface $stateMachineFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        AddressFactoryInterface $addressFactory,
        FactoryInterface $stateMachineFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->addressFactory = $addressFactory;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function handle(AddressOrder $addressOrder)
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $addressOrder->orderToken()]);

        if (null === $order) {
            throw new \LogicException(sprintf('Order with %s token has not been found.', $addressOrder->orderToken()));
        }

        $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);

        if (!$stateMachine->can(OrderCheckoutTransitions::TRANSITION_ADDRESS)) {
            throw new \LogicException(sprintf('Order with %s token cannot be addressed.', $addressOrder->orderToken()));
        }

        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $this->addressFactory->createNew();

        $shippingAddress->setFirstName($addressOrder->shippingAddress()->firstName());
        $shippingAddress->setLastName($addressOrder->shippingAddress()->lastName());
        $shippingAddress->setCity($addressOrder->shippingAddress()->city());
        $shippingAddress->setStreet($addressOrder->shippingAddress()->street());
        $shippingAddress->setCountryCode($addressOrder->shippingAddress()->countryCode());
        $shippingAddress->setPostcode($addressOrder->shippingAddress()->postcode());
        $shippingAddress->setProvinceName($addressOrder->shippingAddress()->provinceName());

        /** @var AddressInterface $billingAddress */
        $billingAddress = $this->addressFactory->createNew();

        $billingAddress->setFirstName($addressOrder->billingAddress()->firstName());
        $billingAddress->setLastName($addressOrder->billingAddress()->lastName());
        $billingAddress->setCity($addressOrder->billingAddress()->city());
        $billingAddress->setStreet($addressOrder->billingAddress()->street());
        $billingAddress->setCountryCode($addressOrder->billingAddress()->countryCode());
        $billingAddress->setPostcode($addressOrder->billingAddress()->postcode());
        $billingAddress->setProvinceName($addressOrder->billingAddress()->provinceName());

        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);

        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_ADDRESS);
    }
}