<template>
  <section id="desktop-app" class="desktop-pricing-section">
    <div class="container">
      <div class="section-header">
        <span class="badge">Desktop App</span>
        <h2>Own Your POS — No Monthly Fees</h2>
        <p class="subtitle">
          ClassicPOS Desktop is a one-time purchase. Pay once, use forever.
          Runs entirely on your machine with full offline capability.
        </p>
      </div>

      <div class="plans-grid">
        <!-- Professional -->
        <div class="plan-card">
          <div class="plan-header">
            <h3>Professional</h3>
            <div class="price">
              <span class="amount">$150</span>
              <span class="period">one-time</span>
            </div>
          </div>

          <div class="best-for">
            <strong>Best for:</strong> Small retail shops, bars, and restaurants with 1-5 locations that need reliable offline POS.
          </div>

          <ul class="features">
            <li><span class="check">&#10003;</span> Full offline POS system</li>
            <li><span class="check">&#10003;</span> USB &amp; serial receipt printing</li>
            <li><span class="check">&#10003;</span> Cash drawer control</li>
            <li><span class="check">&#10003;</span> Sales &amp; inventory reports</li>
            <li><span class="check">&#10003;</span> Multi-branch (up to 5 locations)</li>
            <li><span class="check">&#10003;</span> Barcode scanning</li>
            <li><span class="check">&#10003;</span> Auto-updates (1 year included)</li>
            <li><span class="check">&#10003;</span> Cloudflare Tunnel remote access</li>
          </ul>

          <a href="#buy-desktop" class="btn-buy">Buy Now — $150</a>
          <p class="updates-note">1 year of updates included</p>
        </div>

        <!-- Enterprise -->
        <div class="plan-card enterprise-card">
          <div class="plan-badge">Best Value</div>
          <div class="plan-header">
            <h3>Enterprise</h3>
            <div class="price">
              <span class="amount">$150</span>
              <span class="period">one-time</span>
            </div>
          </div>

          <div class="best-for">
            <strong>Best for:</strong> Enterprise chains and multi-location operations needing custom integrations and priority support.
          </div>

          <ul class="features">
            <li><span class="check">&#10003;</span> Everything in Professional</li>
            <li><span class="check">&#10003;</span> Custom integrations</li>
            <li><span class="check">&#10003;</span> Priority support</li>
            <li><span class="check">&#10003;</span> SLA guarantee</li>
            <li><span class="check">&#10003;</span> Lifetime updates</li>
            <li><span class="check">&#10003;</span> Unlimited branches</li>
            <li><span class="check">&#10003;</span> Unlimited devices</li>
          </ul>

          <a href="#buy-desktop" class="btn-buy enterprise">Buy Now — $150</a>
          <p class="updates-note">Lifetime updates included</p>
        </div>
      </div>

      <!-- Trust badges -->
      <div class="trust-badges">
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>No monthly fees</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>Offline-first</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>Native printing</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>Windows, macOS, Linux</span>
        </div>
      </div>

      <!-- How it works -->
      <div class="how-it-works">
        <h3>How It Works</h3>
        <div class="steps">
          <div class="step">
            <div class="step-num">1</div>
            <h4>Purchase</h4>
            <p>Buy a one-time license for $150. No subscriptions, no recurring fees.</p>
          </div>
          <div class="step">
            <div class="step-num">2</div>
            <h4>Download</h4>
            <p>Download the installer for Windows, macOS, or Linux from GitHub.</p>
          </div>
          <div class="step">
            <div class="step-num">3</div>
            <h4>Install &amp; Activate</h4>
            <p>Run the installer, enter your license key, and start selling.</p>
          </div>
          <div class="step">
            <div class="step-num">4</div>
            <h4>Sell Offline</h4>
            <p>Process sales without internet. Data syncs when you reconnect.</p>
          </div>
        </div>
      </div>

      <!-- Comparison with cloud plans -->
      <div class="comparison">
        <h3>Desktop App vs Cloud Plans</h3>
        <table>
          <thead>
            <tr>
              <th>Feature</th>
              <th>Cloud (from $0/mo)</th>
              <th>Desktop ($150 one-time)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Pricing</td>
              <td>Monthly subscription</td>
              <td><strong>One-time payment</strong></td>
            </tr>
            <tr>
              <td>Hosting</td>
              <td>Managed cloud or self-hosted</td>
              <td><strong>Your machine</strong></td>
            </tr>
            <tr>
              <td>Offline</td>
              <td>Limited</td>
              <td><strong>Full offline mode</strong></td>
            </tr>
            <tr>
              <td>Internet required</td>
              <td>Yes (for most features)</td>
              <td><strong>No</strong></td>
            </tr>
            <tr>
              <td>Printing</td>
              <td>Browser-based</td>
              <td><strong>Native USB/Serial</strong></td>
            </tr>
            <tr>
              <td>Cash drawer</td>
              <td>Limited</td>
              <td><strong>Native control</strong></td>
            </tr>
            <tr>
              <td>Updates</td>
              <td>Automatic (while subscribed)</td>
              <td><strong>1 year / Lifetime</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
// No logic needed — static content
</script>

<style scoped>
.desktop-pricing-section {
  padding: 5rem 0;
  background: #f8fafc;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.badge {
  display: inline-block;
  background: #dbeafe;
  color: #1d4ed8;
  padding: 0.25rem 1rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.section-header h2 {
  font-size: 2.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.75rem;
}

.subtitle {
  font-size: 1.1rem;
  color: #64748b;
  max-width: 600px;
  margin: 0 auto;
}

.plans-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  max-width: 800px;
  margin: 0 auto 3rem;
}

.plan-card {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 2rem;
  position: relative;
  transition: all 0.2s;
}

.plan-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.enterprise-card {
  border-color: #7c3aed;
}

.enterprise-card:hover {
  border-color: #7c3aed;
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
}

.plan-badge {
  position: absolute;
  top: -12px;
  right: 1.5rem;
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  color: white;
  padding: 0.375rem 1rem;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.plan-header h3 {
  margin: 0 0 0.5rem;
  font-size: 1.5rem;
  color: #0f172a;
}

.price {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.amount {
  font-size: 2.5rem;
  font-weight: 800;
  color: #0f172a;
}

.period {
  font-size: 1rem;
  color: #64748b;
}

.best-for {
  background: #f1f5f9;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #475569;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.features {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem;
}

.features li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  font-size: 0.95rem;
  color: #334155;
}

.check {
  color: #22c55e;
  font-weight: bold;
  flex-shrink: 0;
}

.btn-buy {
  display: block;
  width: 100%;
  padding: 0.875rem;
  background: #3b82f6;
  color: white;
  text-align: center;
  text-decoration: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 1rem;
  transition: background 0.2s;
}

.btn-buy:hover {
  background: #2563eb;
}

.btn-buy.enterprise {
  background: #7c3aed;
}

.btn-buy.enterprise:hover {
  background: #6d28d9;
}

.updates-note {
  text-align: center;
  font-size: 0.8rem;
  color: #94a3b8;
  margin-top: 0.75rem;
}

.trust-badges {
  display: flex;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
  margin-bottom: 4rem;
}

.badge-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
  color: #475569;
  font-weight: 500;
}

.badge-icon {
  color: #22c55e;
  font-weight: bold;
  font-size: 1.1rem;
}

.how-it-works {
  margin-bottom: 4rem;
}

.how-it-works h3 {
  text-align: center;
  font-size: 1.5rem;
  margin-bottom: 2rem;
  color: #0f172a;
}

.steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
}

.step {
  text-align: center;
  padding: 1.5rem 1rem;
}

.step-num {
  width: 40px;
  height: 40px;
  background: #3b82f6;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  margin: 0 auto 1rem;
}

.step h4 {
  margin: 0 0 0.5rem;
  color: #0f172a;
}

.step p {
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.5;
}

.comparison {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 2rem;
}

.comparison h3 {
  text-align: center;
  margin-bottom: 1.5rem;
  color: #0f172a;
}

.comparison table {
  width: 100%;
  border-collapse: collapse;
}

.comparison th,
.comparison td {
  padding: 0.75rem 1rem;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
}

.comparison th {
  background: #f8fafc;
  font-weight: 600;
  color: #0f172a;
}

.comparison td {
  color: #475569;
}

@media (max-width: 768px) {
  .plans-grid {
    grid-template-columns: 1fr;
  }
  .steps {
    grid-template-columns: 1fr 1fr;
  }
  .trust-badges {
    flex-direction: column;
    align-items: center;
  }
}
</style>
