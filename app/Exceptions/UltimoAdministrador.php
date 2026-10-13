<?php

namespace App\Exceptions;

use DomainException;

/**
 * FS-ADM-060: o último administrador ativo de um parceiro não pode ser removido,
 * desativado nem rebaixado.
 */
class UltimoAdministrador extends DomainException
{
    public function __construct()
    {
        parent::__construct('O último administrador ativo do parceiro não pode ser removido ou desativado.');
    }
}
