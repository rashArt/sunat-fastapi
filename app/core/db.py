from typing import Generator

from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker, declarative_base

from app.core.config import settings


engine = create_engine(
    settings.DATABASE_URL,
    future=True,
    pool_pre_ping=True,  # verifica la conexión antes de usarla (reconecta si cayó)
)
SessionLocal = sessionmaker(bind=engine, autoflush=False, autocommit=False, future=True)
Base = declarative_base()


def get_db() -> Generator[Session, None, None]:
    """
    Dependencia FastAPI que provee una sesión SQLAlchemy por request.
    Garantiza el cierre de la sesión aunque ocurra una excepción.
    """
    db: Session = SessionLocal()
    try:
        yield db
    finally:
        db.close()


__all__ = ["engine", "SessionLocal", "Base", "get_db"]
