<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Financeiro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Reset básico e tipografia */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
            /* Fundo em gradiente mais moderno */
            background: linear-gradient(135deg, #1a2a6c, #2a3c7c, #3a4e8c);
            position: relative;
        }

        /* Elementos decorativos animados */
        .finance-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: -1;
            animation: float 15s infinite linear;
        }

        .element-1 {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
        }

        .element-2 {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 10%;
            animation-delay: -5s;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        }

        .element-3 {
            width: 80px;
            height: 80px;
            top: 50%;
            left: 5%;
            animation-delay: -10s;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .element-4 {
            width: 120px;
            height: 120px;
            bottom: 10%;
            left: 20%;
            animation-delay: -2s;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        /* Linhas de gráfico animadas */
        .graph-line {
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            z-index: -1;
            animation: graphLine 20s infinite linear;
        }

        .line-1 {
            width: 300px;
            top: 20%;
            left: -300px;
            animation-delay: 0s;
        }

        .line-2 {
            width: 400px;
            bottom: 25%;
            right: -400px;
            animation-delay: -10s;
        }

        /* Container principal com efeito glassmorphism melhorado */
        .login-container {
            width: 420px;
            padding: 2.5rem;
            text-align: center;
            
            /* Efeito de vidro melhorado */
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            
            /* Animação de entrada */
            animation: fadeInUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
            transition: transform 0.5s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        /* Efeito de brilho no container */
        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            animation: rotate 20s linear infinite;
        }

        .login-container h2 {
            margin-bottom: 2rem;
            color: #ffffff;
            font-weight: 600;
            font-size: 28px;
            letter-spacing: 0.5px;
            position: relative;
            display: inline-block;
        }

        .login-container h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #4facfe, #00f2fe);
            border-radius: 3px;
        }

        /* Grupo de Input com Ícone melhorado */
        .input-group {
            position: relative;
            margin-bottom: 1.8rem;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 16px 16px 16px 50px;
            
            /* Inputs com transparência melhorada */
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            
            border-radius: 12px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 400;
            transition: all 0.3s ease;
        }
        
        /* Cor do placeholder */
        .login-container input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .login-container input:focus {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.4);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
        }

        .login-container input:focus + i {
            color: #4facfe;
            transform: translateY(-50%) scale(1.1);
        }

        /* Botão com novo estilo moderno */
        .login-container button {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 6px 20px rgba(79, 172, 254, 0.3);
            position: relative;
            overflow: hidden;
        }

        .login-container button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .login-container button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.5);
        }

        .login-container button:hover::before {
            left: 100%;
        }

        /* Link de esqueci a senha */
        .forgot-password {
            margin-top: 1.5rem;
            text-align: center;
        }

        .forgot-password a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #4facfe;
            text-decoration: underline;
        }

        /* Animação de moedas caindo */
        .coin {
            position: absolute;
            width: 20px;
            height: 20px;
            background: radial-gradient(circle at 30% 30%, #FFD700, #FFA500);
            border-radius: 50%;
            z-index: -1;
            animation: coinDrop 8s infinite linear;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .coin:nth-child(1) {
            left: 5%;
            top: -20px;
            animation-delay: 0s;
        }

        .coin:nth-child(2) {
            left: 15%;
            top: -20px;
            animation-delay: 1s;
        }

        .coin:nth-child(3) {
            left: 25%;
            top: -20px;
            animation-delay: 2s;
        }

        .coin:nth-child(4) {
            left: 75%;
            top: -20px;
            animation-delay: 3s;
        }

        .coin:nth-child(5) {
            left: 85%;
            top: -20px;
            animation-delay: 4s;
        }

        .coin:nth-child(6) {
            left: 95%;
            top: -20px;
            animation-delay: 5s;
        }

        /* Símbolos de dólar flutuantes */
        .dollar-sign {
            position: absolute;
            color: rgba(255, 255, 255, 0.2);
            font-size: 24px;
            font-weight: bold;
            z-index: -1;
            animation: dollarFloat 20s infinite linear;
            user-select: none;
        }

        .dollar-1 {
            top: 15%;
            left: 8%;
            animation-delay: 0s;
        }

        .dollar-2 {
            top: 25%;
            right: 12%;
            animation-delay: -4s;
        }

        .dollar-3 {
            bottom: 20%;
            left: 12%;
            animation-delay: -8s;
        }

        .dollar-4 {
            bottom: 30%;
            right: 8%;
            animation-delay: -12s;
        }

        .dollar-5 {
            top: 40%;
            left: 5%;
            animation-delay: -16s;
        }

        /* Loading animation */
        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
            color: rgba(255, 255, 255, 0.8);
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #4facfe;
            animation: spin 1s ease-in-out infinite;
            display: inline-block;
            margin-right: 10px;
        }

        /* Animações */
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
            100% {
                transform: translateY(0) rotate(360deg);
            }
        }

        @keyframes graphLine {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(calc(100vw + 400px));
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes coinDrop {
            0% {
                transform: translateY(-20px) rotate(0deg);
                opacity: 1;
            }
            80% {
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes dollarFloat {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.3;
            }
            90% {
                opacity: 0.3;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        /* Responsividade */
        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 2rem 1.5rem;
            }
            
            .finance-element, .graph-line, .dollar-sign {
                display: none;
            }
        }
    </style>

</head>
<body>

    <!-- Elementos decorativos animados -->
    <div class="finance-element element-1"></div>
    <div class="finance-element element-2"></div>
    <div class="finance-element element-3"></div>
    <div class="finance-element element-4"></div>
    
    <!-- Linhas de gráfico animadas -->
    <div class="graph-line line-1"></div>
    <div class="graph-line line-2"></div>
    
    <!-- Moedas caindo -->
    <div class="coin"></div>
    <div class="coin"></div>
    <div class="coin"></div>
    <div class="coin"></div>
    <div class="coin"></div>
    <div class="coin"></div>

    <!-- Símbolos de dólar flutuantes -->
    <div class="dollar-sign dollar-1">$</div>
    <div class="dollar-sign dollar-2">$</div>
    <div class="dollar-sign dollar-3">$</div>
    <div class="dollar-sign dollar-4">$</div>
    <div class="dollar-sign dollar-5">$</div>

    <div class="login-container">
        <h2>Sistema Financeiro</h2>
        
        <form id="loginForm" action="processar_login.php" method="POST">
            
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" id="login" name="login" placeholder="Usuário" required>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="senha" name="senha" placeholder="Senha" required>
            </div>
            
            <button type="submit" id="submitBtn">
                <span id="btnText">Entrar</span>
            </button>
            
            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                Validando credenciais...
            </div>
            

        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const erro = urlParams.get('erro');
            
            if (erro) {
                let titulo = '';
                let texto = '';
                let icone = 'error';

                switch (erro) {
                    case 'login_invalido':
                        titulo = 'Erro!';
                        texto = 'Login ou senha inválidos. Tente novamente.';
                        break;
                    case 'usuario_inativo':
                        titulo = 'Acesso Negado';
                        texto = 'Este usuário está inativo no sistema.';
                        break;
                    case 'campos_vazios':
                        titulo = 'Atenção!';
                        texto = 'Por favor, preencha todos os campos.';
                        icone = 'warning';
                        break;
                    case 'db_error':
                        titulo = 'Erro no Servidor';
                        texto = 'Ocorreu um problema. Tente novamente mais tarde.';
                        break;
                    default:
                        titulo = 'Oops...';
                        texto = 'Ocorreu um erro inesperado.';
                }

                Swal.fire({
                    icon: icone,
                    title: titulo,
                    text: texto,
                    background: 'rgba(26, 42, 108, 0.9)',
                    color: '#fff',
                    backdrop: 'rgba(0,0,0,0.5)',
                    confirmButtonColor: '#4facfe',
                    confirmButtonText: 'Entendido'
                });
            }

            // Sistema de validação do formulário
            const loginForm = document.getElementById('loginForm');
            const loading = document.getElementById('loading');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');

            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('login').value;
                const password = document.getElementById('senha').value;
                
                // Validação básica no front-end
                if (!username || !password) {
                    e.preventDefault();
                    showError('Por favor, preencha todos os campos.');
                    return;
                }
                
                // Mostrar loading
                showLoading(true);
                
                // O formulário será enviado normalmente para o PHP
                // O PHP fará a validação real no servidor
            });

            function showLoading(show) {
                if (show) {
                    loading.style.display = 'block';
                    submitBtn.disabled = true;
                    btnText.textContent = 'Validando...';
                } else {
                    loading.style.display = 'none';
                    submitBtn.disabled = false;
                    btnText.textContent = 'Entrar';
                }
            }

            function showError(message) {
                // Efeito de shake no formulário
                loginForm.classList.add('shake');
                setTimeout(() => {
                    loginForm.classList.remove('shake');
                }, 500);

                Swal.fire({
                    icon: 'error',
                    title: 'Erro no Login',
                    text: message,
                    background: 'rgba(26, 42, 108, 0.9)',
                    color: '#fff',
                    backdrop: 'rgba(0,0,0,0.5)',
                    confirmButtonColor: '#4facfe',
                    confirmButtonText: 'Tentar Novamente'
                });
            }

            // Se houver um parâmetro de sucesso na URL (para demonstração)
            const success = urlParams.get('success');
            if (success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Login realizado com sucesso!',
                    text: 'Bem-vindo ao Sistema Financeiro',
                    background: 'rgba(26, 42, 108, 0.9)',
                    color: '#fff',
                    backdrop: 'rgba(0,0,0,0.5)',
                    confirmButtonColor: '#4bb71b',
                    confirmButtonText: 'Acessar Sistema'
                });
            }
        });
    </script>

</body>
</html>