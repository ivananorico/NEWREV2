// revenue2/citizen_dashboard/digital/js/payment.js
// Additional JavaScript functions can go here

// Export payment functions for other systems to use
window.DigitalPayment = {
    // Function to generate payment data for external systems
    generatePaymentData: function(amount, purpose, options = {}) {
        const baseUrl = window.location.origin + '/revenue2/citizen_dashboard/digital/';
        const paymentData = {
            amount: amount,
            purpose: purpose,
            ...options
        };
        
        // Encode data
        const encodedData = btoa(JSON.stringify(paymentData));
        
        return {
            url: baseUrl + 'index.php?payment_data=' + encodedData,
            data: paymentData
        };
    },
    
    // Function to check payment status
    checkPaymentStatus: async function(paymentId) {
        try {
            const response = await fetch('payment_api.php?action=check_payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ payment_id: paymentId })
            });
            return await response.json();
        } catch (error) {
            console.error('Payment check error:', error);
            return { success: false, message: 'Network error' };
        }
    }
};