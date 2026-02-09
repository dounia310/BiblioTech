<?php
require_once 'connexion.php';
require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --success: #10b981;
            --error: #ef4444;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-md: 16px;
            --transition: 0.3s ease;
        }

        body { font-family: 'Poppins', sans-serif; background: var(--gray-50); color: var(--dark); line-height: 1.7; }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 24px; }
        faq-welcome {
            text-align: center;
            padding: 4rem 1rem 3rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #2d5a87;
            border-radius: 0 0 24px 24px;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-md);
        }

        .faq-welcome h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            margin-bottom: 1rem;
        }

        .faq-welcome p {
            font-size: 1.25rem;
            max-width: 700px;
            margin: 0 auto 2rem;
            opacity: 0.95;
        }

        .search-box {
            max-width: 600px;
            margin: 0 auto 2.5rem;
            display: flex;
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }

        .search-box input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            font-size: 1.05rem;
            outline: none;
        }

        .search-box button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 1.8rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-box button:hover {
            background: var(--primary-dark);
        }

        .no-result-cta {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            text-align: center;
            margin: 2rem 0;
        }

        .no-result-cta p {
            font-size: 1.15rem;
            font-weight: 500;
            margin-bottom: 1.2rem;
            color: #475569;
        }

        .btn-contact {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--primary);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .btn-contact:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(30,58,95,0.2);
        }

        .faq-hero {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 6rem 2rem 4rem;
            text-align: center;
        }
        .faq-hero h1 { font-family: 'Playfair Display', serif; font-size: 3.2rem; margin-bottom: 1rem; }
        .faq-hero p { font-size: 1.25rem; max-width: 700px; margin: 0 auto; opacity: 0.9; }

        .faq-section { padding: 5rem 0; }
        .faq-grid { display: grid; gap: 1.2rem; }

        .faq-item {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .faq-question {
            padding: 1.5rem 2rem;
            font-size: 1.15rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
            transition: background var(--transition);
        }

        .faq-question:hover { background: var(--gray-100); }

        .faq-question i {
            transition: transform var(--transition);
            color: var(--primary);
            font-size: 1.1rem;
        }

        .faq-item.active .faq-question i { transform: rotate(180deg); }

        .faq-answer {
            padding: 0 2rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
            color: var(--gray-700);
        }

        .faq-item.active .faq-answer {
            max-height: 1200px;
            padding: 1.8rem 2rem;
        }
    </style>
</head>
<body>

    <section class="faq-welcome">
        <div class="container">
            <h2>COMMENT PEUT-ON VOUS AIDER ?</h2>
            <p>Tapez votre demande et laissez-vous guider par nos rubriques dédiées</p>

            <div class="search-box">
                <input type="text" placeholder="Rechercher dans l'aide..." id="faq-search">
                <button type="button"><i class="fas fa-search"></i></button>
            </div>

            <div class="no-result-cta">
                <p>VOUS N'AVEZ PAS TROUVÉ VOTRE RÉPONSE ?</p>
                <a href="contact.php" class="btn-contact">
                    <i class="fas fa-envelope"></i> ENVOYER UNE DEMANDE
                </a>
            </div>
        </div>
    </section>
    

    <section class="faq-section">
        <div class="container">
            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">
                        Comment créer un compte ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Cliquez sur « Créer un compte » en haut à droite.<br>
                        Remplissez votre e-mail, mot de passe et informations personnelles.<br>
                        Validez votre adresse e-mail via le lien reçu.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Comment emprunter un livre ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p><p>Pour emprunter un livre en ligne, suivez ces étapes simples :</p>

<ol>
    <li>Connectez-vous à votre compte (ou créez-en un si ce n’est pas déjà fait).</li>
    <li>Recherchez le livre dans le catalogue (via la barre de recherche ou les filtres).</li>
    <li>Cliquez sur le livre → puis sur le bouton <strong>« Emprunter »</strong> (visible sur la page de détails si le livre est disponible).</li>
    <li>Choisissez la durée d’emprunt souhaitée (7, 14 ou 21 jours maximum).</li>
    <li>Confirmez votre demande → vous recevrez immédiatement un e-mail de confirmation avec la date limite de retour.</li>
</ol>

<p><strong>Important :</strong> Si le livre est actuellement emprunté ou indisponible, vous pouvez cliquer sur <strong>« Réserver »</strong>. Vous serez averti(e) automatiquement par e-mail dès qu’il sera de retour et disponible pour vous.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Puis-je prolonger un emprunt ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, jusqu’à 2 prolongations de 14 jours chacune, tant que personne ne l’a réservé.<br>
                        Allez dans « Mes emprunts » → « Prolonger ».</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                <strong>Puis-je acheter et emprunter en même temps ?</strong><br>
                <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                      <p> Oui absolument ! Ajoutez les livres que vous voulez acheter et ceux que vous voulez emprunter dans le même panier.  
                        Bonus fidélité : pour tout achat d’au moins 200 DH, votre premier emprunt est offert (gratuit).</p> 
                        </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Que se passe-t-il en cas de retard ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>5 DH par jour de retard.<br>
                        Après 30 jours : facture du prix du livre + 100 DH de frais.<br>
                        Votre compte sera suspendu jusqu’au retour.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Comment payer ma commande ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Carte bancaire (CMI), virement bancaire ou cash à la livraison (Casablanca, Rabat, Marrakech uniquement).</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Livraison gratuite ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui dès 300 DH d’achat.<br>
                        Sinon : 25 DH standard / 40 DH express (24h).</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Retour possible ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>14 jours pour les achats (livre intact, emballage d’origine).<br>
                        Retour gratuit via point relais ou La Poste (étiquette fournie).</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        Comment contacter le support ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>support@bibliotech.ma<br>
                           WhatsApp : +212 6 XX XX XX XX<br>
                           Du lundi au samedi 9h–19h</p>
                    </div>
                </div>
            </div>
    </div>
</div>
    </section>

    <script>
        document.querySelectorAll('.faq-question').forEach(q => {
            q.addEventListener('click', () => {
                const item = q.parentElement;
                item.classList.toggle('active');
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
</body>
</html>