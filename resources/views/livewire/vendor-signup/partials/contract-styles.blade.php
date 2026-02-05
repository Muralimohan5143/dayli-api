@once
<style>
    .dayli-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0
    }

    .dayli-table th,
    .dayli-table td {
        padding: 10px 12px;
        vertical-align: middle
    }

    .dayli-table thead th {
        background: #F5ECD9;
        color: #111827;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb
    }

    .dayli-input {
        max-width: 130px
    }

    /* 🔥 Remove opacity dimming */
    .is-off {
        opacity: 1 !important;
        color: #000 !important;
    }

    /* 🔥 Force inputs (even disabled) to show black text */
    .form-control[disabled],
    .form-control[readonly] {
        color: #000 !important;
        -webkit-text-fill-color: #000 !important;
        /* Safari */
        opacity: 1 !important;
    }
</style>
@endonce