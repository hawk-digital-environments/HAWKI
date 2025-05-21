<div class="bg-white rounded shadow-sm p-4 mb-4">
    <h4>Model Settings Information</h4>
    <p>
        These settings control the behavior of the language model during queries. Depending on the provider and model, different parameters may be available.
    </p>

    <div class="mt-3">
        <h5>Common Parameters</h5>
        <ul>
            <li><strong>temperature</strong>: Controls the creativity/randomness of the responses (0.0-2.0, lower values = more deterministic)</li>
            <li><strong>max_tokens</strong>: Maximum length of the generated response</li>
            <li><strong>top_p</strong>: Alternative to temperature, controls the token selection probability (0.0-1.0)</li>
            <li><strong>frequency_penalty</strong>: Reduces repetitions by penalizing frequently used words (-2.0 to 2.0)</li>
            <li><strong>presence_penalty</strong>: Increases diversity by encouraging new topics (-2.0 to 2.0)</li>
        </ul>
    </div>

    <div class="mt-4">
        <h5>Example JSON for OpenAI Models</h5>
        <pre class="bg-light p-3 rounded"><code>{
    "temperature": 0.7,
    "max_tokens": 800,
    "top_p": 1.0,
    "frequency_penalty": 0.0,
    "presence_penalty": 0.0
}</code></pre>
    </div>

    <div class="mt-4">
        <h5>Example JSON for Anthropic Claude</h5>
        <pre class="bg-light p-3 rounded"><code>{
    "temperature": 0.7,
    "max_tokens_to_sample": 800,
    "top_p": 0.9,
    "top_k": 50
}</code></pre>
    </div>

    <div class="alert alert-info mt-4">
        <strong>Note:</strong> Refer to the respective model provider's documentation for specific parameters and recommendations.
    </div>
</div>
