<?php
require_once 'config/config.php';

// Only check for dashboard redirect if not explicitly showing login modal
if (!isset($_GET['action']) || $_GET['action'] !== 'login') {
    checkDashboardRedirect();
}

$showLoginModal = isset($_GET['action']) && $_GET['action'] === 'login';

if (isLoggedIn()) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            redirect('/admin/dashboard.php');
            break;
        case 'staff':
            redirect('/staff/dashboard.php');
            break;
        case 'member':
            redirect('/member/dashboard.php');
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['error' => null, 'redirect' => null];

    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $response['error'] = "Invalid request";
        echo json_encode($response);
        exit;
    }

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (!checkLoginAttempts($username)) {
        $waitTime = getLoginLockoutTime($username);
        $response['error'] = "Too many failed attempts. Please try again in $waitTime minutes.";
        echo json_encode($response);
        exit;
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Successful login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = sanitizeOutput($user['username']);
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = sanitizeOutput($user['full_name']);
                
                // Clear login attempts
                clearLoginAttempts($username);
                
                // Log successful login
                logAdminAction($user['id'], 'login', null, 'Successful login');
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        $response['redirect'] = SITE_URL . '/admin/dashboard.php';
                        break;
                    case 'staff':
                        $response['redirect'] = SITE_URL . '/staff/dashboard.php';
                        break;
                    case 'member':
                        $response['redirect'] = SITE_URL . '/member/dashboard.php';
                        break;
                }
                echo json_encode($response);
                exit;
            }
        }
        
        // Record failed attempt
        recordLoginAttempt($username);
        $remainingAttempts = getRemainingLoginAttempts($username);
        $response['error'] = "Invalid username or password. $remainingAttempts attempts remaining.";
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VikingsFit- Your Fitness Journey Starts Here</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/images/gym-hero.jpg');
            background-size: cover;
            background-position: center;
            height: 80vh;
            display: flex;
            align-items: center;
            color: white;
        }
        .feature-icon {
            font-size: 4rem;
            color: #1565c0;
        }
        .plan-card {
            height: 100%;
        }
        .testimonial {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
            height: 100%;
        }
        .section {
            padding: 40px 0;
        }
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        
        body {
            padding-top: 64px; /* Height of the navbar */
        }
        
        @media only screen and (max-width: 600px) {
            body {
                padding-top: 56px; /* Height of mobile navbar */
            }
        }

        /* Smooth scroll padding for sections */
        section {
            scroll-margin-top: 64px;
        }
        
        @media only screen and (max-width: 600px) {
            section {
                scroll-margin-top: 56px;
            }
        }

        /* Add active state for nav links */
        .nav-active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .card .card-content .card-title {
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .card h4 {
            font-size: 2.5rem;
            margin: 10px 0;
            font-weight: bold;
        }
        .card .grey-text {
            font-size: 1.1rem;
        }
        .card .card-action {
            padding: 20px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        .card .btn-large {
            margin: 0;
            width: 80%;
        }
        .card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .card .card-content {
            flex-grow: 1;
        }
        
        #loginModal .error-message {
            color: #f44336;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            display: none;
        }
        
        #loginModal .error-message.show {
            display: block;
        }
        
        #loginModal .input-field {
            margin-bottom: 20px;
        }
        
        #registerModal .error-message {
            color: #f44336;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0 0 0;
        }
        .plan-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #424242;
        }
        .plan-features i {
            font-size: 16px;
        }
        .plan-description {
            min-height: 60px;
            margin-bottom: 20px;
            color: #616161;
            font-size: 0.95rem;
            text-align: center;
        }
        .card .card-content {
            padding: 24px 24px 0 24px;
        }
        .card .card-action {
            padding: 16px;
            border-top: 1px solid rgba(160, 160, 160, 0.2);
            background: rgba(0, 0, 0, 0.02);
        }
        .card-title {
            font-size: 1.5rem !important;
            font-weight: 500 !important;
            color: #1565C0 !important;
        }
        .card h4 {
            margin: 0 0 8px 0;
            font-size: 2rem;
        }
        .card .grey-text {
            font-size: 1.1rem;
        }
        @media only screen and (max-width: 600px) {
            .card h4 {
                font-size: 1.8rem;
            }
            .card .grey-text {
                font-size: 1rem;
            }
            .plan-features li {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="blue darken-3">
        <div class="nav-wrapper container">
            <a href="<?php echo SITE_URL; ?>/index.php" class="brand-logo">VikingsFit</a>
            <a href="#" data-target="mobile-nav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
            <ul id="nav-mobile" class="right hide-on-med-and-down">
                <li><a href="#features">Features</a></li>
                <li><a href="#plans">Plans</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#!" class="btn white black-text modal-trigger" data-target="loginModal">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- Mobile Navigation -->
    <ul class="sidenav" id="mobile-nav">
        <li><a href="#features">Features</a></li>
        <li><a href="#plans">Plans</a></li>
        <li><a href="#testimonials">Testimonials</a></li>
        <li><a href="#!" class="modal-trigger" data-target="loginModal">Login</a></li>
    </ul>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col s12 m8">
                    <h1>Your Fitness Journey Starts Here</h1>
                    <p class="flow-text">Join VikingsFit today and transform your life with our state-of-the-art facilities and expert trainers.</p>
                    <div style="margin-top: 40px;">
                        <a href="#!" class="btn-large waves-effect waves-light blue darken-3 modal-trigger" data-target="registerModal">
                            Join Us
                            <i class="material-icons right">fitness_center</i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="section">
        <div class="container">
            <h2 class="center-align">Why Choose Us</h2>
            <div class="row">
                <div class="col s12 m4 center-align">
                    <i class="material-icons feature-icon">fitness_center</i>
                    <h5>Modern Equipment</h5>
                    <p>State-of-the-art fitness equipment for all your workout needs</p>
                </div>
                <div class="col s12 m4 center-align">
                    <i class="material-icons feature-icon">group</i>
                    <h5>Expert Trainers</h5>
                    <p>Professional trainers to guide you through your fitness journey</p>
                </div>
                <div class="col s12 m4 center-align">
                    <i class="material-icons feature-icon">schedule</i>
                    <h5>Flexible Hours</h5>
                    <p>Open 24/7 to fit your busy schedule</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership Plans Section -->
    <section id="plans" class="section">
        <div class="container">
            <div class="row">
                <div class="col s12 center">
                    <h3>Membership Plans</h3>
                    <p class="flow-text grey-text">Choose the plan that fits your needs</p>
                </div>
            </div>
            <div class="row">
                <?php
                // Fetch active plans
                $plans_query = "SELECT * FROM plans WHERE deleted_at IS NULL ORDER BY price ASC";
                $plans_result = $conn->query($plans_query);

                if ($plans_result && $plans_result->num_rows > 0):
                    while($plan = $plans_result->fetch_assoc()):
                        $features = !empty($plan['features']) ? json_decode($plan['features'], true) : [];
                ?>
                    <div class="col s12 m6 l4">
                        <div class="card hoverable">
                            <div class="card-content">
                                <span class="card-title center"><?php echo htmlspecialchars($plan['name']); ?></span>
                                <div class="center" style="padding: 20px 0;">
                                    <h4 class="blue-text">₱<?php echo number_format($plan['price'], 2); ?></h4>
                                    <p class="grey-text"><?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'Month' : 'Months'; ?></p>
                                </div>
                                <div class="plan-description">
                                    <?php if (!empty($plan['description'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($features)): ?>
                                    <ul class="plan-features">
                                        <?php foreach ($features as $feature): ?>
                                            <li>
                                                <i class="material-icons tiny blue-text">check_circle</i>
                                                <?php echo htmlspecialchars($feature); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="card-action center-align">
                                <a href="#!" class="btn-large waves-effect waves-light blue darken-3 modal-trigger" data-target="registerModal">
                                    Join Now
                                    <i class="material-icons right">arrow_forward</i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="col s12">
                        <p class="center-align grey-text">No membership plans available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="section">
        <div class="container">
            <h2 class="center-align">What Our Members Say</h2>
            <div class="row">
                <div class="col s12 m4">
                    <div class="testimonial">
                        <i class="material-icons">format_quote</i>
                        <p>"Best gym I've ever been to! The trainers are amazing and the equipment is top-notch."</p>
                        <p><strong>- John Doe</strong></p>
                    </div>
                </div>
                <div class="col s12 m4">
                    <div class="testimonial">
                        <i class="material-icons">format_quote</i>
                        <p>"I've achieved my fitness goals thanks to the amazing support from the staff."</p>
                        <p><strong>- Jane Smith</strong></p>
                    </div>
                </div>
                <div class="col s12 m4">
                    <div class="testimonial">
                        <i class="material-icons">format_quote</i>
                        <p>"Flexible hours and great atmosphere. Couldn't ask for more!"</p>
                        <p><strong>- Mike Johnson</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <h4 class="center-align blue-text text-darken-3">Welcome Back!</h4>
            <div id="loginError" class="red-text center-align" style="display: none;">
                <i class="material-icons tiny">error</i>
                <span id="loginErrorText"></span>
            </div>
            <form id="loginForm" class="row">
                <div class="input-field col s12">
                    <i class="material-icons prefix">person</i>
                    <input type="text" id="login_username" name="username" required>
                    <label for="login_username">Username</label>
                </div>
                <div class="input-field col s12">
                    <i class="material-icons prefix">lock</i>
                    <input type="password" id="login_password" name="password" required>
                    <label for="login_password">Password</label>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="col s12 center-align">
                    <button class="btn-large waves-effect waves-light blue darken-3" type="submit">
                        Login
                        <i class="material-icons right">send</i>
                    </button>
                </div>
            </form>
            <div class="center-align" style="margin-top: 20px;">
                <p>New member? <a href="#!" class="modal-trigger" data-target="registerModal" onclick="M.Modal.getInstance(document.getElementById('loginModal')).close();">Register here</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <h4 class="center-align blue-text text-darken-3">Create Account</h4>
            <div id="registerError" class="red-text center-align" style="display: none;">
                <i class="material-icons tiny">error</i>
                <span></span>
            </div>
            <div class="row">
                <form id="registerForm" class="col s12" method="POST">
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="text" id="register_username" name="username" required>
                            <label for="register_username">Username</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="email" id="register_email" name="email" required>
                            <label for="register_email">Email</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="password" id="register_password" name="password" required>
                            <label for="register_password">Password</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="password" id="register_confirm_password" name="confirm_password" required>
                            <label for="register_confirm_password">Confirm Password</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="text" id="register_full_name" name="full_name" required>
                            <label for="register_full_name">Full Name</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="text" id="register_contact_number" name="contact_number" required>
                            <label for="register_contact_number">Contact Number</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-field col s12">
                            <textarea id="register_address" name="address" class="materialize-textarea" required></textarea>
                            <label for="register_address">Address</label>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col s12 center-align">
                            <button class="btn-large waves-effect waves-light blue darken-3" type="submit">
                                Register
                                <i class="material-icons right">send</i>
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col s12 center-align">
                            Already have an account? 
                            <a href="#!" class="modal-trigger" data-target="loginModal">Login here</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="page-footer blue darken-3">
        <div class="container">
            <div class="row">
                <div class="col s12 m6">
                    <h5 class="white-text">VikingsFit </h5>
                    <p class="grey-text text-lighten-4">Your journey to a healthier lifestyle starts here.</p>
                </div>
                <div class="col s12 m4 offset-m2">
                    <h5 class="white-text">Quick Links</h5>
                    <ul>
                        <li><a class="grey-text text-lighten-3" href="#features">Features</a></li>
                        <li><a class="grey-text text-lighten-3" href="#plans">Plans</a></li>
                        <li><a class="grey-text text-lighten-3 modal-trigger" href="#!" data-target="loginModal">Login</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-copyright">
            <div class="container">
                &copy; 2024 Vikings Fitness Gym
                <a class="grey-text text-lighten-4 right" href="#!">Terms of Service</a>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        const SITE_URL = '<?php echo SITE_URL; ?>';

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all modals
            var modals = document.querySelectorAll('.modal');
            var modalInstances = M.Modal.init(modals);

            // Open login modal if requested
            <?php if ($showLoginModal): ?>
                var loginModal = document.getElementById('loginModal');
                var instance = M.Modal.getInstance(loginModal);
                instance.open();
            <?php endif; ?>

            // Initialize mobile navigation
            var elems = document.querySelectorAll('.sidenav');
            var instances = M.Sidenav.init(elems);

            // Initialize select
            var selects = document.querySelectorAll('select');
            var selectInstances = M.FormSelect.init(selects);

            // Login form submission
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(SITE_URL + '/index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('loginError').style.display = 'block';
                        document.getElementById('loginErrorText').textContent = data.error;
                    } else {
                        // Redirect based on role
                        window.location.href = data.redirect;
                    }
                })
                .catch(error => {
                    document.getElementById('loginError').style.display = 'block';
                    document.getElementById('loginErrorText').textContent = 'An error occurred. Please try again.';
                });
            });

            // Register form handling
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('ajax/register.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        M.toast({html: 'Registration successful! Redirecting...'});
                        setTimeout(() => {
                            window.location.href = SITE_URL + '/member/dashboard.php';
                        }, 1500);
                    } else {
                        const errorDiv = document.getElementById('registerError');
                        errorDiv.querySelector('span').textContent = result.message;
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error processing registration'});
                });
            });

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    if (this.getAttribute('href').length > 1) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        const targetElement = document.querySelector(targetId);
                        
                        if (targetElement) {
                            targetElement.scrollIntoView({
                                behavior: 'smooth'
                            });
                            
                            // Update active state in navigation
                            document.querySelectorAll('.nav-active').forEach(el => el.classList.remove('nav-active'));
                            this.parentElement.classList.add('nav-active');
                        }
                    }
                });
            });

            // Intersection Observer for section highlighting
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('nav ul li a[href^="#"]');

            const observerOptions = {
                rootMargin: '-30% 0px -70% 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(link => {
                            if (link.getAttribute('href') === `#${id}`) {
                                document.querySelectorAll('.nav-active').forEach(el => el.classList.remove('nav-active'));
                                link.parentElement.classList.add('nav-active');
                            }
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(section => {
                observer.observe(section);
            });

            // Close mobile nav when clicking a link
            document.querySelectorAll('.sidenav a').forEach(link => {
                link.addEventListener('click', () => {
                    const sidenav = document.querySelector('.sidenav');
                    const instance = M.Sidenav.getInstance(sidenav);
                    instance.close();
                });
            });

            // Get all links that have hash (#) in them
            const links = document.querySelectorAll('a[href^="#"]');
            
            links.forEach(link => {
                if (link.getAttribute('href').length > 1) { // Ignore links that are just "#"
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        const targetElement = document.querySelector(targetId);
                        
                        if (targetElement) {
                            targetElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                            
                            // Update URL without scrolling
                            history.pushState(null, null, targetId);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>