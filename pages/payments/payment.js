document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const amount = document.getElementById('amount').value;
    const method = document.getElementById('method').value;
    const resultDiv = document.getElementById('result');

    resultDiv.innerHTML = "Processing payment...";

    try {
        const response = await fetch('/api/modules/payment/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: `amount=${encodeURIComponent(amount)}&method=${encodeURIComponent(method)}`
        });

        const data = await response.json();

        console.log(data);

        if (data.success) {
            if (data.should_redirect) {
                resultDiv.innerHTML = `
                <p>Redirecting to ${method.toUpperCase()}...</p>
                <a href="${data.redirect_url}" target="_blank">Click here if not redirected</a>
            `;
                window.location.href = data.redirect_url; // auto redirect
            }
        } else {
            resultDiv.innerHTML = `<p style="color:red;">Error: ${data.message}</p>`;
        }

    } catch (err) {
        resultDiv.innerHTML = `<p style="color:red;">Network error. Try again.</p>`;
    }
});
