(function () {
  function postToVerify(payload) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'payments/verify_payment.php';

    Object.keys(payload).forEach(function (key) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = payload[key];
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  }

  function fetchOrder(planId, source) {
    return fetch('payments/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan_id: planId,
        source: source
      })
    }).then(function (res) { return res.json(); });
  }

  function startRazorpayCheckout(planId, source) {
    if (!window.Razorpay) {
      alert('Razorpay SDK not loaded.');
      return;
    }

    fetchOrder(planId, source).then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.message) ? data.message : 'Unable to start payment.');
        return;
      }

      var options = {
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        name: 'Certanity Robotics',
        description: data.description,
        order_id: data.order_id,
        prefill: data.prefill || {},
        method: {
          card: true,
          upi: true,
          netbanking: false,
          wallet: false,
          emi: false,
          paylater: false
        },
        theme: { color: '#10256D' },
        handler: function (response) {
          postToVerify({
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature,
            plan_id: planId,
            source: source
          });
        }
      };

      var rzp = new Razorpay(options);
      rzp.open();
    }).catch(function () {
      alert('Unable to connect to payment service.');
    });
  }

  function fetchTopupOrder(amountUsd, source) {
    return fetch('payments/create_topup_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        amount_usd: amountUsd,
        source: source
      })
    }).then(function (res) { return res.json(); });
  }

  function startRazorpayTopUp(amountUsd, source) {
    if (!window.Razorpay) {
      alert('Razorpay SDK not loaded.');
      return;
    }

    var amount = parseFloat(amountUsd);
    if (!amount || amount < 1) {
      alert('Enter a top-up amount of at least $1.');
      return;
    }

    fetchTopupOrder(amount, source || 'billing').then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.message) ? data.message : 'Unable to start payment.');
        return;
      }

      var options = {
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        name: 'Certanity Robotics',
        description: data.description,
        order_id: data.order_id,
        prefill: data.prefill || {},
        method: {
          card: true,
          upi: true,
          netbanking: false,
          wallet: false,
          emi: false,
          paylater: false
        },
        theme: { color: '#10256D' },
        handler: function (response) {
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = 'payments/verify_topup.php';

          [{
            n: 'razorpay_order_id', v: response.razorpay_order_id
          }, {
            n: 'razorpay_payment_id', v: response.razorpay_payment_id
          }, {
            n: 'razorpay_signature', v: response.razorpay_signature
          }, {
            n: 'source', v: source || 'billing'
          }].forEach(function (f) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = f.n;
            input.value = f.v;
            form.appendChild(input);
          });

          document.body.appendChild(form);
          form.submit();
        }
      };

      var rzp = new Razorpay(options);
      rzp.open();
    }).catch(function () {
      alert('Unable to connect to payment service.');
    });
  }

  window.startRazorpayCheckout = startRazorpayCheckout;
  window.startRazorpayTopUp = startRazorpayTopUp;
})();
