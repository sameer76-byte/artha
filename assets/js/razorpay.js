// ============================================================
//  assets/js/razorpay.js
//  Handles the Razorpay checkout popup flow.
//  Include this on checkout.php AFTER cart.js
//  Also include the Razorpay SDK:
//  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
// ============================================================

/**
 * Main entry point — called when user clicks "Place Order"
 * for any non-COD payment method.
 *
 * @param {number} orderId     - Our internal order ID (returned by place.php)
 * @param {string} orderNumber - Human-readable order number
 */
async function initRazorpayPayment(orderId, orderNumber) {
    // 1. Create a Razorpay order on our server
    let rzpData;
    try {
        const res = await fetch('/api/payments/create_order.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ order_id: orderId }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        rzpData = json.data;
    } catch (err) {
        showToast('Could not initiate payment: ' + err.message, 'error');
        return;
    }

    // 2. Open Razorpay checkout popup
    const options = {
        key          : rzpData.key_id,
        amount       : rzpData.amount,          // paise
        currency     : rzpData.currency,
        name         : document.title.split('—')[1]?.trim() || 'MyShop',
        description  : 'Order ' + orderNumber,
        order_id     : rzpData.rzp_order_id,
        prefill      : rzpData.prefill,
        theme        : { color: '#1a1612' },
        modal: {
            ondismiss: function () {
                showToast('Payment cancelled. Your order is saved — you can pay later.', 'info');
                // Redirect to order detail so user can retry
                setTimeout(() => {
                    window.location.href = `/pages/order_detail.php?id=${orderId}`;
                }, 1500);
            }
        },
        handler: async function (response) {
            // 3. Verify signature on our server
            await verifyPayment(orderId, orderNumber, response);
        },
    };

    const rzp = new Razorpay(options);

    rzp.on('payment.failed', function (response) {
        showToast('Payment failed: ' + (response.error.description || 'Unknown error'), 'error');
        setTimeout(() => {
            window.location.href = `/pages/order_detail.php?id=${orderId}`;
        }, 2000);
    });

    rzp.open();
}

/**
 * Verify the payment signature with our server.
 * On success, redirect to order success page.
 */
async function verifyPayment(orderId, orderNumber, rzpResponse) {
    showToast('Verifying payment…', 'info');

    try {
        const res = await fetch('/api/payments/verify.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                order_id              : orderId,
                razorpay_order_id     : rzpResponse.razorpay_order_id,
                razorpay_payment_id   : rzpResponse.razorpay_payment_id,
                razorpay_signature    : rzpResponse.razorpay_signature,
            }),
        });
        const json = await res.json();

        if (json.success) {
            showToast('Payment successful!', 'success');
            setTimeout(() => {
                window.location.href =
                    `/pages/order_success.php?order_id=${orderId}&order_number=${encodeURIComponent(orderNumber)}`;
            }, 800);
        } else {
            showToast('Verification failed: ' + json.message, 'error');
        }
    } catch (err) {
        showToast('Network error during verification. Please contact support.', 'error');
    }
}