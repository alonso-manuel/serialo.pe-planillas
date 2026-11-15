export type VideoGuia = {
    clave: string;
    numero: number;
    titulo: string;
    descripcion: string;
    disponible: boolean;
    url: string;
};

export type SeccionGuia = {
    titulo: string;
    videos: VideoGuia[];
};
