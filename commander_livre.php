<?php
// Formulaire accessible depuis la page de recherche quand livre non trouvé
?>
<div class="commande-form">
    <h3>📖 Livre non disponible ?</h3>
    <p>Demandez-nous de l'ajouter à notre catalogue !</p>
    
    <form action="traiter_suggestion.php" method="POST">
        <input type="hidden" name="membre_id" value="<?= $_SESSION['user_id'] ?>">
        
        <div class="form-group">
            <label>Titre du livre *</label>
            <input type="text" name="titre" required class="form-control">
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <label>Auteur *</label>
                <input type="text" name="auteur" required class="form-control">
            </div>
            <div class="col-md-6">
                <label>ISBN (si connu)</label>
                <input type="text" name="isbn" class="form-control">
            </div>
        </div>
        
        <div class="form-group">
            <label>Année de publication</label>
            <input type="number" name="annee" min="1900" max="2030" class="form-control">
        </div>
        
        <div class="form-group">
            <label>Pourquoi voulez-vous ce livre ? *</label>
            <textarea name="raison" rows="3" required class="form-control"></textarea>
        </div>
        
        <div class="form-group">
            <label>Préférence</label>
            <select name="type_demande" class="form-control">
                <option value="achat">Achat (à acheter)</option>
                <option value="location">Location seulement</option>
                <option value="les_deux">Les deux</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Envoyer la demande
        </button>
    </form>
</div>