<?php $u = current_user(); ?>
<?php if ($u): ?>
        </section>
        <footer class="app-footer">
            <span>SmartClinic+ Prototype · SENG205</span>
            <span>Secure role-based healthcare workflow</span>
        </footer>
    </main>
</div>
<?php else: ?>
    <footer class="public-footer">
        <div>
            <strong>SmartClinic+</strong>
            <p>Interactive SENG205 healthcare management prototype.</p>
        </div>
        <div>
            <span>Privacy by design</span>
            <span>Role-based access</span>
            <span>Secure data handling</span>
        </div>
    </footer>
<?php endif; ?>

<script src="assets/js/app.js"></script>
</body>
</html>