"""
Servicio de almacenamiento de artefactos.

Gestiona el guardado de XML unsigned, XML signed, CDR y otros archivos
en el sistema de archivos local (o futuramente S3).

Equivalente al trait StorageDocument en CoreFacturalo.

Estructura de directorios:
  storage/{RUC}/{filename}/unsigned/{archivo}.xml
  storage/{RUC}/{filename}/signed/{archivo}.xml
  storage/{RUC}/{filename}/cdr/{archivo}.zip
  storage/{RUC}/{filename}/pdf/{archivo}.pdf
"""
import logging
from pathlib import Path

from app.core.config import settings

logger = logging.getLogger(__name__)

_VALID_TYPES = {"unsigned", "signed", "cdr", "pdf", "qr"}


class StorageService:
    """Gestiona el almacenamiento de artefactos por compañía y documento."""

    def __init__(self, ruc: str) -> None:
        self._root = Path(settings.STORAGE_LOCAL_PATH) / ruc

    def save(self, artifact_type: str, filename: str, data: bytes) -> Path:
        """
        Guarda un artefacto en disco.

        Args:
            artifact_type: 'unsigned' | 'signed' | 'cdr' | 'pdf' | 'qr'
            filename:      Nombre del archivo con extensión.
            data:          Bytes a guardar.

        Returns:
            Path absoluto del archivo guardado.
        """
        if artifact_type not in _VALID_TYPES:
            raise ValueError(f"Tipo de artefacto no válido: {artifact_type}")

        # Obtener nombre base sin extensión para usarlo como carpeta
        base_name = Path(filename).stem

        folder = self._root / base_name / artifact_type
        folder.mkdir(parents=True, exist_ok=True)

        file_path = folder / filename
        file_path.write_bytes(data)
        logger.debug("Artefacto guardado: %s", file_path)
        return file_path

    def read(self, artifact_type: str, filename: str) -> bytes:
        """Lee un artefacto guardado previamente."""
        base_name = Path(filename).stem
        file_path = self._root / base_name / artifact_type / filename
        if not file_path.exists():
            raise FileNotFoundError(f"Artefacto no encontrado: {file_path}")
        return file_path.read_bytes()

    def exists(self, artifact_type: str, filename: str) -> bool:
        """Verifica si un artefacto ya existe en storage."""
        base_name = Path(filename).stem
        return (self._root / base_name / artifact_type / filename).exists()

    def get_path(self, artifact_type: str, filename: str) -> Path:
        """Retorna la ruta absoluta del artefacto sin verificar su existencia."""
        base_name = Path(filename).stem
        return self._root / base_name / artifact_type / filename
