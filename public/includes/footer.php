<?php
/**
 * Footer Template
 */
?>
                <?php if (isLoggedIn()): ?>
                </div><!-- /.main-content -->
            </div><!-- /#content -->
        </div><!-- /.wrapper -->
                <?php else: ?>
        </div><!-- /.auth-wrapper -->
                <?php endif; ?>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Scripts -->
    <script src="assets/js/app.js"></script>
</body>
</html>
