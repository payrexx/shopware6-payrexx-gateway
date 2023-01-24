import template from './sw-order-detail.html.twig';

const {Component, Context} = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-detail', {
    template,

    data() {
        return {
            latestTransaction: null,
        }
    },
    created() {
        this.loadRelevantData();
    },
    computed: {
        isPayrexxPayment() {
            if (!this.latestTransaction) return false;
            let paymentMethod = this.latestTransaction.paymentMethod;

            return (paymentMethod.distinguishableName.includes('Payrexx'));
        },
        isRefundable() {
            let refundableStates = ['paid', 'refunded_partially', 'paid_partially'];
            return (this.latestTransaction && refundableStates.includes(this.latestTransaction.stateMachineState.technicalName));
        }
    },

    methods: {
        loadRelevantData() {
            const orderCriteria = new Criteria(1, 1);
            orderCriteria.addAssociation('transactions');
            orderCriteria.addAssociation('transactions.stateMachineState');
            orderCriteria.addAssociation('transactions.paymentMethod');

            this.orderRepository.get(this.orderId, Context.api, orderCriteria).then((order) => {
                this.latestTransaction = order.transactions.last();
            });
        }
    }
});
