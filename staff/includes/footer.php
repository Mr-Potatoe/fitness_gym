        </div>
    </main>
    
    <!-- Materialize JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    
    <!-- Initialize Materialize components -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all Materialize components
            M.AutoInit();
            
            // Initialize sidenav
            var elems = document.querySelectorAll('.sidenav');
            M.Sidenav.init(elems);
            
            // Initialize dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-trigger');
            M.Dropdown.init(dropdowns, {
                constrainWidth: false,
                coverTrigger: false
            });
        });
    </script>
</body>
</html> 